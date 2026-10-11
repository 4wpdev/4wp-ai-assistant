<?php
/**
 * Job Extractor
 *
 * Extracts structured skills/requirements/recommendations/methodology
 * from `job` CPT posts using the active AI provider.
 *
 * Storage: two dedicated tables (see JobSkillsInstaller), not postmeta.
 * At the scale this runs at (1000+ vacancies), postmeta would mean one
 * large text blob per post that's never queried directly — only ever
 * json-decoded row by row to aggregate. Real rows make frequency counting
 * (group 3 of the plan) a plain SQL GROUP BY instead of a full PHP scan.
 *
 * @package ForWP\AIAssistant\RAG
 */

namespace ForWP\AIAssistant\RAG;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\AIAssistant\AI\ProviderManager;

class JobExtractor
{
	/**
	 * Target CPT
	 */
	const POST_TYPE = 'job';

	/**
	 * The four extracted fields, also used as `field_type` values in the
	 * job_skills table.
	 */
	const FIELDS = ['skills', 'requirements', 'recommendations', 'methodology'];

	/**
	 * Empty result shape
	 *
	 * @return array
	 */
	protected static function emptyResult(): array
	{
		return array_fill_keys(self::FIELDS, []);
	}

	/**
	 * Register the cleanup hooks. Without this, deleting a job post leaves
	 * its rows in forwp_job_skills / forwp_job_extractions behind — an
	 * off-topic vacancy (wrong niche, scraper mistake) that gets deleted
	 * would keep polluting the Skill Frequency stats forever.
	 *
	 * Hooked on both events so every deletion path is covered:
	 * - wp_trash_post: the normal "Delete" click in wp-admin (soft delete)
	 * - before_delete_post: permanent delete (from Trash, or force_delete)
	 */
	public static function init(): void
	{
		add_action('wp_trash_post', [self::class, 'handleJobRemoved']);
		add_action('before_delete_post', [self::class, 'handleJobRemoved']);
	}

	/**
	 * @param int $post_id
	 */
	public static function handleJobRemoved(int $post_id): void
	{
		$post = get_post($post_id);

		if (!$post || $post->post_type !== self::POST_TYPE) {
			return;
		}

		self::purgeJob($post_id);
	}

	/**
	 * Remove a job's extracted rows entirely — both the fact rows
	 * (forwp_job_skills) and its "done" marker (forwp_job_extractions).
	 * Does NOT touch the shared dictionary (forwp_skills / aliases): a
	 * canonical term stays defined even if no job currently references it,
	 * since other jobs may still use it, or may in the future.
	 *
	 * @param int $post_id
	 */
	public static function purgeJob(int $post_id): void
	{
		global $wpdb;
		JobSkillsInstaller::maybeInstall();

		$skills_table = JobSkillsInstaller::skillsTable();
		$extractions_table = JobSkillsInstaller::extractionsTable();

		$wpdb->delete($skills_table, ['job_id' => $post_id], ['%d']);
		$wpdb->delete($extractions_table, ['job_id' => $post_id], ['%d']);
	}

	/**
	 * Build extraction prompt
	 *
	 * @param string $content Raw post content (may include HTML)
	 * @return string
	 */
	public static function buildPrompt(string $content): string
	{
		$clean = trim(wp_strip_all_tags($content));

		return "Analyze the job posting below and return STRICTLY valid JSON "
			. "(no markdown fences, no text before or after), a single object, in this shape:\n"
			. "{\"skills\": [\"...\"], \"requirements\": [\"...\"], \"recommendations\": [\"...\"], \"methodology\": [\"...\"]}\n\n"
			. "Rules:\n"
			. "- skills: specific technologies/tools/programming languages, regardless of which section they appear in\n"
			. "- requirements: mandatory requirements (experience, education, language level) — NOT technologies\n"
			. "- recommendations: anything marked as 'nice to have' / bonus / desired — NOT technologies\n"
			. "- methodology: work approaches and processes (OOP, code review, CI/CD, Agile, AI-agents workflow, etc.)\n"
			. "- if a category is empty, use []\n"
			. "- do not invent anything — use only the text below\n"
			. "- the job posting text may be in any language (English, Ukrainian, etc.); your JSON keys, structure and values must still follow this exact schema\n\n"
			. "Job posting:\n" . $clean;
	}

	/**
	 * Parse raw AI text response into the expected array shape
	 *
	 * @param string $raw Raw text from AI provider
	 * @return array|\WP_Error
	 */
	protected static function parseResponse(string $raw): array|\WP_Error
	{
		$raw = trim($raw);
		// Strip ```json ... ``` fences if the model added them anyway
		$raw = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
		$raw = trim($raw);

		$data = json_decode($raw, true);

		if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
			// Most likely cause: the response got cut off mid-JSON because
			// max_tokens was too low for a long posting (lots of skills/
			// requirements to list). Show a snippet of what actually came
			// back so this is diagnosable from the UI, not just the logs.
			$snippet = mb_substr($raw, -200);

			return new \WP_Error(
				'json_parse_error',
				sprintf(
					/* translators: %s: end of the raw AI response that failed to parse as JSON */
					__('Could not parse the AI provider\'s JSON response (likely truncated). End of response: "%s"', '4wp-ai-assistant'),
					$snippet
				),
				['raw' => $raw]
			);
		}

		$data = wp_parse_args($data, self::emptyResult());

		// Force each field to be a flat array of trimmed, non-empty strings
		foreach (self::FIELDS as $field) {
			if (!is_array($data[$field])) {
				$data[$field] = [];
				continue;
			}
			$data[$field] = array_values(array_filter(array_map(function ($v) {
				$v = is_string($v) ? trim($v) : null;
				return $v !== '' ? $v : null;
			}, $data[$field])));
		}

		return $data;
	}

	/**
	 * Extract structured data from a single job post
	 *
	 * @param int $post_id Job post ID
	 * @param bool $force Re-run extraction even if already cached
	 * @return array|\WP_Error
	 */
	public static function extractOne(int $post_id, bool $force = false): array|\WP_Error
	{
		$post = get_post($post_id);

		if (!$post || $post->post_type !== self::POST_TYPE) {
			return new \WP_Error(
				'invalid_post',
				sprintf(__('Post #%d not found, or it is not a job posting (%s).', '4wp-ai-assistant'), $post_id, self::POST_TYPE)
			);
		}

		if (!$force) {
			$existing = self::getStored($post_id);
			if ($existing !== null) {
				return $existing;
			}
		}

		$provider = ProviderManager::getActiveProvider();

		if (!$provider || !$provider->isConfigured()) {
			return new \WP_Error('no_provider', __('No AI provider is configured. Please check your .env file.', '4wp-ai-assistant'));
		}

		$prompt = self::buildPrompt($post->post_content);

		$response = self::sendWithRetry($provider, $prompt, [
			'temperature' => 0.1,
			// Long postings (big requirement/nice-to-have lists) can produce
			// a long JSON array of skills — 1024 was cutting some of those
			// off mid-response, which then failed to parse as JSON.
			'max_tokens' => 2048,
		]);

		if (is_wp_error($response)) {
			// Intentionally not recorded as "done" — leaving no row means
			// the job stays "pending" and gets picked up on the next run.
			return $response;
		}

		$raw = $response['message'] ?? '';
		$parsed = self::parseResponse($raw);

		if (is_wp_error($parsed)) {
			return $parsed;
		}

		self::saveExtraction($post_id, $parsed, (string) ($response['model'] ?? ''));

		return $parsed;
	}

	/**
	 * Persist a successful extraction: replace this job's rows in the
	 * skills table and mark it done in the extractions table.
	 *
	 * @param int $post_id
	 * @param array $parsed
	 * @param string $model
	 */
	protected static function saveExtraction(int $post_id, array $parsed, string $model = ''): void
	{
		global $wpdb;
		JobSkillsInstaller::maybeInstall();

		$skills_table = JobSkillsInstaller::skillsTable();
		$extractions_table = JobSkillsInstaller::extractionsTable();
		$now = current_time('mysql');

		// Clear any previous rows for this job first (covers force re-runs
		// and keeps the table free of stale duplicates).
		$wpdb->delete($skills_table, ['job_id' => $post_id], ['%d']);

		foreach (self::FIELDS as $field) {
			foreach ($parsed[$field] as $value) {
				$clean_value = mb_substr((string) $value, 0, 255);

				$wpdb->insert($skills_table, [
					'job_id' => $post_id,
					'field_type' => $field,
					'value' => $clean_value,
					'canonical_skill_id' => SkillDictionary::resolve($clean_value),
					'created_at' => $now,
				], ['%d', '%s', '%s', '%d', '%s']);
			}
		}

		$wpdb->replace($extractions_table, [
			'job_id' => $post_id,
			'status' => 'done',
			'model' => $model,
			'extracted_at' => $now,
		], ['%d', '%s', '%s', '%s']);
	}

	/**
	 * Send a message to the AI provider, automatically waiting out and
	 * retrying a rate-limit (429) response instead of failing the request.
	 *
	 * Groq's free/on-demand tier enforces a tokens-per-minute budget; its
	 * error message includes the exact wait time (e.g. "try again in 5.5s").
	 *
	 * @param \ForWP\AIAssistant\AI\ProviderInterface $provider
	 * @param string $prompt
	 * @param array $options
	 * @param int $max_retries
	 * @return array|\WP_Error
	 */
	protected static function sendWithRetry($provider, string $prompt, array $options, int $max_retries = 3)
	{
		$attempt = 0;

		while (true) {
			$response = $provider->sendMessage($prompt, $options);

			if (!is_wp_error($response)) {
				return $response;
			}

			$data = $response->get_error_data();
			$status = is_array($data) ? ($data['status'] ?? null) : null;
			$message = $response->get_error_message();
			$is_rate_limit = ($status === 429) || (stripos($message, 'rate limit') !== false);

			if (!$is_rate_limit || $attempt >= $max_retries) {
				return $response;
			}

			$wait = 5.0;
			if (preg_match('/try again in\s*([\d.]+)\s*s/i', $message, $m)) {
				$wait = (float) $m[1];
			}

			// Small buffer on top of what the provider tells us, so we don't
			// come back a fraction of a second too early and get limited again.
			sleep((int) ceil($wait + 1));
			$attempt++;
		}
	}

	/**
	 * Extract a batch of not-yet-processed job posts
	 *
	 * @param int $limit Max number of posts to process in this run
	 * @param bool $force Re-run extraction even for posts already processed
	 * @return array Map of post_id => result array or ['error' => message]
	 */
	public static function extractBatch(int $limit = 10, bool $force = false): array
	{
		global $wpdb;

		// Batch runs can take a while once rate-limit waits are involved —
		// don't let PHP's default execution-time limit cut it short.
		if (function_exists('set_time_limit')) {
			@set_time_limit(0);
		}

		JobSkillsInstaller::maybeInstall();

		$all_ids = get_posts([
			'post_type' => self::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
		]);

		if (!$force) {
			$extractions_table = JobSkillsInstaller::extractionsTable();
			$done_ids = array_map('intval', $wpdb->get_col(
				"SELECT job_id FROM {$extractions_table} WHERE status = 'done'"
			));
			$all_ids = array_values(array_diff($all_ids, $done_ids));
		}

		$ids = array_slice($all_ids, 0, $limit);
		$results = [];

		foreach ($ids as $id) {
			$result = self::extractOne((int) $id, $force);

			$results[$id] = is_wp_error($result)
				? ['error' => $result->get_error_message()]
				: $result;

			// Proactive spacing between requests (on top of the reactive
			// retry-on-429 above) — keeps us further from the TPM ceiling.
			if (count($ids) > 1) {
				sleep(2);
			}
		}

		return $results;
	}

	/**
	 * Get overall extraction progress stats
	 *
	 * @return array{total:int, extracted:int, remaining:int}
	 */
	public static function getStats(): array
	{
		global $wpdb;
		JobSkillsInstaller::maybeInstall();

		$counts = wp_count_posts(self::POST_TYPE);
		$total = (int) ($counts->publish ?? 0);

		$extractions_table = JobSkillsInstaller::extractionsTable();
		$extracted = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$extractions_table} WHERE status = 'done'"
		);

		return [
			'total' => $total,
			'extracted' => $extracted,
			'remaining' => max(0, $total - $extracted),
		];
	}

	/**
	 * Get the stored extraction result for a post, if it was successfully
	 * processed. Returns null for a job that hasn't been extracted yet
	 * (including one whose last attempt errored — it stays retryable).
	 *
	 * @param int $post_id
	 * @return array|null
	 */
	public static function getStored(int $post_id): ?array
	{
		global $wpdb;
		JobSkillsInstaller::maybeInstall();

		$extractions_table = JobSkillsInstaller::extractionsTable();
		$is_done = $wpdb->get_var($wpdb->prepare(
			"SELECT 1 FROM {$extractions_table} WHERE job_id = %d AND status = 'done'",
			$post_id
		));

		if (!$is_done) {
			return null;
		}

		return self::fetchRows($post_id);
	}

	/**
	 * Read a job's extracted rows back into the {skills, requirements, ...}
	 * shape used everywhere else in this class.
	 *
	 * @param int $post_id
	 * @return array
	 */
	protected static function fetchRows(int $post_id): array
	{
		global $wpdb;
		$skills_table = JobSkillsInstaller::skillsTable();

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT field_type, value FROM {$skills_table} WHERE job_id = %d ORDER BY id ASC",
			$post_id
		));

		$result = self::emptyResult();

		foreach ($rows as $row) {
			if (isset($result[$row->field_type])) {
				$result[$row->field_type][] = $row->value;
			}
		}

		return $result;
	}
}

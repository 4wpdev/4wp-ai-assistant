<?php
/**
 * Job Extractor Test/Run Page
 *
 * Admin UI to run and inspect job vacancy extraction (skills, requirements,
 * recommendations, methodology) — RAG pipeline, group 1.
 *
 * Fully AJAX-driven: no page reloads. Each extraction (single or batch) is
 * a separate admin-ajax.php call, so a rate-limit wait on one vacancy never
 * blocks the whole page and progress is visible live.
 *
 * @package ForWP\AIAssistant\Admin
 */

namespace ForWP\AIAssistant\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\AIAssistant\RAG\JobExtractor;

class JobExtractorPage
{
	/**
	 * Register AJAX handler(s). Called from plugin bootstrap.
	 */
	public static function initAjax(): void
	{
		add_action('wp_ajax_job_extractor_extract', [self::class, 'ajaxExtract']);
		add_action('wp_ajax_job_extractor_get_original', [self::class, 'ajaxGetOriginal']);
	}

	/**
	 * AJAX: return the original vacancy (title, content, source meta) for
	 * the compare panel. Goes through admin-ajax rather than the public
	 * REST API so it shares the exact same auth path as extraction — one
	 * less thing (permalinks, REST visibility, security-plugin rules) that
	 * can silently break it.
	 */
	public static function ajaxGetOriginal(): void
	{
		if (!check_ajax_referer('job_extractor_ajax', 'nonce', false)) {
			wp_send_json_error([
				'message' => __('Security check failed. Please reload the page and try again.', '4wp-ai-assistant'),
			], 403);
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error([
				'message' => __('You do not have permission to do this.', '4wp-ai-assistant'),
			], 403);
		}

		$post_id = (int) ($_POST['post_id'] ?? 0);
		$post = $post_id > 0 ? get_post($post_id) : null;

		if (!$post || $post->post_type !== JobExtractor::POST_TYPE) {
			wp_send_json_error([
				'message' => sprintf(__('Vacancy #%d not found.', '4wp-ai-assistant'), $post_id),
			]);
		}

		wp_send_json_success([
			'id' => $post->ID,
			'title' => get_the_title($post),
			'content' => apply_filters('the_content', $post->post_content),
			'source_url' => get_post_meta($post->ID, 'source_url', true),
			'vacancy_date' => get_post_meta($post->ID, 'vacancy_date', true),
		]);
	}

	/**
	 * AJAX: extract a single job post.
	 *
	 * Always responds with well-formed JSON — including on a failed nonce or
	 * capability check — so the front-end never has to guess at a blank or
	 * non-JSON response.
	 */
	public static function ajaxExtract(): void
	{
		if (!check_ajax_referer('job_extractor_ajax', 'nonce', false)) {
			wp_send_json_error([
				'message' => __('Security check failed. Please reload the page and try again.', '4wp-ai-assistant'),
			], 403);
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error([
				'message' => __('You do not have permission to do this.', '4wp-ai-assistant'),
			], 403);
		}

		$post_id = (int) ($_POST['post_id'] ?? 0);
		$force = !empty($_POST['force']);

		if ($post_id <= 0) {
			wp_send_json_error([
				'message' => __('Invalid vacancy ID.', '4wp-ai-assistant'),
			]);
		}

		$result = JobExtractor::extractOne($post_id, $force);

		if (is_wp_error($result)) {
			wp_send_json_error([
				'message' => $result->get_error_message(),
				'stats' => JobExtractor::getStats(),
			]);
		}

		wp_send_json_success([
			'post_id' => $post_id,
			'title' => get_the_title($post_id),
			'result' => $result,
			'stats' => JobExtractor::getStats(),
		]);
	}

	/**
	 * Render admin page
	 */
	public static function render(): void
	{
		// Jobs list for the dropdown (id, title, already-extracted flag)
		$jobs_list = get_posts([
			'post_type' => JobExtractor::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby' => 'ID',
			'order' => 'DESC',
		]);

		$all_ids = [];
		$pending_ids = [];

		foreach ($jobs_list as $job) {
			$all_ids[] = $job->ID;
			if (JobExtractor::getStored($job->ID) === null) {
				$pending_ids[] = $job->ID;
			}
		}

		$stats = JobExtractor::getStats();

		// Strings used by the inline script below. Kept in PHP (wrapped in
		// __()) rather than hardcoded in JS so they go through the same
		// translation pipeline (text domain: 4wp-ai-assistant) as the rest
		// of the page.
		$strings = [
			'selectOrEnterId' => __('Please select or enter a vacancy ID.', '4wp-ai-assistant'),
			'loading' => __('Loading…', '4wp-ai-assistant'),
			'failedToLoadOriginal' => __('Failed to load the original posting.', '4wp-ai-assistant'),
			'runningAiRequest' => __('Running AI request (this can take a while if we hit a rate limit)…', '4wp-ai-assistant'),
			'error' => __('Error', '4wp-ai-assistant'),
			'networkError' => __('Network error:', '4wp-ai-assistant'),
			'noVacanciesToProcess' => __('No vacancies to process.', '4wp-ai-assistant'),
			// %1$s / %2$s are replaced in JS — keep both placeholders.
			'processingStatus' => __('Processing %1$s of %2$s…', '4wp-ai-assistant'),
			'doneStatus' => __('Done (%1$s).', '4wp-ai-assistant'),
			'source' => __('Source:', '4wp-ai-assistant'),
			'vacancyDate' => __('Vacancy date:', '4wp-ai-assistant'),
			'selectVacancyPrompt' => __('Select a vacancy and click "Extract Data".', '4wp-ai-assistant'),
		];

		self::enqueueStyles();
		?>
		<div class="wrap wp-ai-assistant-wrap">
			<div class="wp-ai-assistant-header">
				<h1 class="wp-ai-assistant-title">
					<span class="dashicons dashicons-portfolio"></span>
					<?php esc_html_e('Job Extractor', '4wp-ai-assistant'); ?>
				</h1>
				<p class="wp-ai-assistant-description">
					<?php esc_html_e('Extracts skills, requirements, recommendations and methodology from vacancies (job CPT) via the active AI provider. Everything runs over AJAX — the page never reloads.', '4wp-ai-assistant'); ?>
				</p>
			</div>

			<!-- Stats -->
			<div class="wp-ai-assistant-stats">
				<div class="wp-ai-assistant-stat-card">
					<div class="stat-number" id="stat-total"><?php echo esc_html($stats['total']); ?></div>
					<div class="stat-label"><?php esc_html_e('Total vacancies', '4wp-ai-assistant'); ?></div>
				</div>
				<div class="wp-ai-assistant-stat-card">
					<div class="stat-number" id="stat-extracted"><?php echo esc_html($stats['extracted']); ?></div>
					<div class="stat-label"><?php esc_html_e('Extracted', '4wp-ai-assistant'); ?></div>
				</div>
				<div class="wp-ai-assistant-stat-card">
					<div class="stat-number" id="stat-remaining"><?php echo esc_html($stats['remaining']); ?></div>
					<div class="stat-label"><?php esc_html_e('Remaining', '4wp-ai-assistant'); ?></div>
				</div>
			</div>

			<!-- Batch run -->
			<div class="wp-ai-assistant-test-container">
				<h2><?php esc_html_e('Batch processing', '4wp-ai-assistant'); ?></h2>
				<p class="description" style="margin-bottom:15px;">
					<?php esc_html_e('Groq (on-demand tier) caps usage at ~8000 tokens/min. Each vacancy is processed as its own sequential AJAX request; if a request hits the rate limit it waits it out and retries automatically — progress is shown live below.', '4wp-ai-assistant'); ?>
				</p>
				<div class="wp-ai-assistant-form-group">
					<label for="limit" class="wp-ai-assistant-label"><?php esc_html_e('How many vacancies to process at once', '4wp-ai-assistant'); ?></label>
					<input type="number" id="limit" class="wp-ai-assistant-input" value="5" min="1" max="25" style="max-width:120px;">
				</div>
				<div class="wp-ai-assistant-form-group">
					<label>
						<input type="checkbox" id="force">
						<?php esc_html_e('Overwrite already-processed vacancies (force)', '4wp-ai-assistant'); ?>
					</label>
				</div>
				<button type="button" id="job-extractor-batch-btn" class="button button-primary">
					<?php esc_html_e('Run batch', '4wp-ai-assistant'); ?>
				</button>
				<span id="job-extractor-batch-status" style="margin-left:10px;color:#646970;"></span>

				<div id="job-extractor-batch-log" style="margin-top:15px;"></div>
			</div>

			<!-- Single post test -->
			<div class="wp-ai-assistant-test-container">
				<h2><?php esc_html_e('Test on a single vacancy', '4wp-ai-assistant'); ?></h2>

				<div class="wp-ai-assistant-form-group">
					<label for="post_id_select" class="wp-ai-assistant-label"><?php esc_html_e('Select a vacancy from the list', '4wp-ai-assistant'); ?></label>
					<select id="post_id_select" class="wp-ai-assistant-input">
						<option value=""><?php esc_html_e('— select a vacancy —', '4wp-ai-assistant'); ?></option>
						<?php foreach ($jobs_list as $job): ?>
							<?php $is_extracted = JobExtractor::getStored($job->ID) !== null; ?>
							<option value="<?php echo esc_attr($job->ID); ?>">
								<?php echo $is_extracted ? '✓ ' : ''; ?>#<?php echo esc_html($job->ID); ?> — <?php echo esc_html($job->post_title); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="wp-ai-assistant-form-group">
					<label for="post_id_manual" class="wp-ai-assistant-label"><?php esc_html_e('...or enter an ID manually', '4wp-ai-assistant'); ?></label>
					<input type="number" id="post_id_manual" class="wp-ai-assistant-input" style="max-width:150px;">
				</div>
				<div class="wp-ai-assistant-form-group">
					<label>
						<input type="checkbox" id="force_single">
						<?php esc_html_e('Overwrite if already processed (force)', '4wp-ai-assistant'); ?>
					</label>
				</div>
				<button type="button" id="job-extractor-single-btn" class="button button-secondary">
					<?php esc_html_e('Extract Data', '4wp-ai-assistant'); ?>
				</button>

				<div class="job-extractor-compare" style="margin-top:20px;">
					<div class="job-extractor-col">
						<h3><?php esc_html_e('Extraction result', '4wp-ai-assistant'); ?></h3>
						<div id="job-extractor-result">
							<p style="color:#646970;"><?php esc_html_e('Select a vacancy and click "Extract Data".', '4wp-ai-assistant'); ?></p>
						</div>
					</div>
					<div class="job-extractor-col">
						<h3><?php esc_html_e('Original vacancy', '4wp-ai-assistant'); ?></h3>
						<div id="job-extractor-original" class="job-extractor-original"></div>
					</div>
				</div>
			</div>
		</div>

		<script>
		(function () {
			const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
			const nonce = <?php echo wp_json_encode(wp_create_nonce('job_extractor_ajax')); ?>;
			const allIds = <?php echo wp_json_encode(array_map('intval', $all_ids)); ?>;
			const pendingIds = <?php echo wp_json_encode(array_map('intval', $pending_ids)); ?>;
			const i18n = <?php echo wp_json_encode($strings); ?>;

			const selectEl = document.getElementById('post_id_select');
			const manualEl = document.getElementById('post_id_manual');
			const forceSingleEl = document.getElementById('force_single');
			const singleBtn = document.getElementById('job-extractor-single-btn');
			const resultEl = document.getElementById('job-extractor-result');
			const originalEl = document.getElementById('job-extractor-original');

			function escapeHtml(str) {
				const div = document.createElement('div');
				div.textContent = String(str);
				return div.innerHTML;
			}

			function errorBox(message) {
				return '<div class="job-extractor-error-box">' +
					'<strong>' + escapeHtml(i18n.error) + ':</strong> ' + escapeHtml(message) +
					'</div>';
			}

			function currentPostId() {
				const manual = parseInt(manualEl.value, 10);
				if (manual > 0) return manual;
				const sel = parseInt(selectEl.value, 10);
				return sel > 0 ? sel : 0;
			}

			function updateStats(stats) {
				if (!stats) return;
				document.getElementById('stat-total').textContent = stats.total;
				document.getElementById('stat-extracted').textContent = stats.extracted;
				document.getElementById('stat-remaining').textContent = stats.remaining;
			}

			async function loadOriginal(id) {
				if (!id) { originalEl.innerHTML = ''; return; }
				originalEl.innerHTML = '<p style="color:#646970;">' + escapeHtml(i18n.loading) + '</p>';

				const body = new URLSearchParams({
					action: 'job_extractor_get_original',
					nonce: nonce,
					post_id: id
				});

				let res, data;
				try {
					res = await fetch(ajaxUrl, { method: 'POST', body: body });
				} catch (e) {
					originalEl.innerHTML = errorBox(i18n.networkError + ' ' + e.message);
					return;
				}
				try {
					data = await res.json();
				} catch (e) {
					// The server didn't return JSON at all (e.g. a PHP fatal
					// printed HTML) — surface the HTTP status, not a canned line.
					originalEl.innerHTML = errorBox(i18n.networkError + ' HTTP ' + res.status + ' — ' + i18n.failedToLoadOriginal);
					return;
				}

				if (!data || !data.success) {
					const detail = (data && data.data && data.data.message) || (i18n.failedToLoadOriginal);
					originalEl.innerHTML = errorBox(detail);
					return;
				}

				const job = data.data;
				const src = job.source_url || '';
				const vdate = job.vacancy_date || '';
				originalEl.innerHTML =
					'<p class="job-extractor-original-title"><strong>' + escapeHtml(job.title) + '</strong> <span class="indexed-id">#' + job.id + '</span></p>' +
					'<p class="job-extractor-original-meta">' +
						(src ? escapeHtml(i18n.source) + ' <a href="' + escapeHtml(src) + '" target="_blank" rel="noopener">' + escapeHtml(src) + '</a><br>' : '') +
						(vdate ? escapeHtml(i18n.vacancyDate) + ' ' + escapeHtml(vdate) : '') +
					'</p>' +
					'<div class="job-extractor-original-content">' + job.content + '</div>';
			}

			/**
			 * Calls the extraction AJAX endpoint and always resolves to a
			 * plain object describing the outcome — never throws for an
			 * application-level (non-network) failure, so callers don't
			 * need their own try/catch around "expected" errors.
			 */
			async function extractOne(id, force) {
				const body = new URLSearchParams({
					action: 'job_extractor_extract',
					nonce: nonce,
					post_id: id,
					force: force ? '1' : ''
				});

				let res;
				try {
					res = await fetch(ajaxUrl, { method: 'POST', body: body });
				} catch (e) {
					return { ok: false, message: i18n.networkError + ' ' + e.message };
				}

				let data;
				try {
					data = await res.json();
				} catch (e) {
					return { ok: false, message: i18n.networkError + ' HTTP ' + res.status };
				}

				if (data && data.success) {
					return { ok: true, payload: data.data };
				}

				return {
					ok: false,
					message: (data && data.data && data.data.message) || (i18n.error),
					stats: data && data.data && data.data.stats
				};
			}

			selectEl.addEventListener('change', function () {
				manualEl.value = this.value;
				loadOriginal(currentPostId());
			});
			manualEl.addEventListener('change', function () {
				loadOriginal(currentPostId());
			});

			singleBtn.addEventListener('click', async function () {
				const id = currentPostId();
				if (!id) { alert(i18n.selectOrEnterId); return; }
				singleBtn.disabled = true;
				resultEl.innerHTML = '<p style="color:#646970;">' + escapeHtml(i18n.runningAiRequest) + '</p>';
				loadOriginal(id);

				const outcome = await extractOne(id, forceSingleEl.checked);
				if (outcome.ok) {
					resultEl.innerHTML = '<pre class="job-extractor-pre">' + escapeHtml(JSON.stringify(outcome.payload.result, null, 2)) + '</pre>';
					updateStats(outcome.payload.stats);
				} else {
					resultEl.innerHTML = errorBox(outcome.message);
					updateStats(outcome.stats);
				}
				singleBtn.disabled = false;
			});

			// Batch
			const batchBtn = document.getElementById('job-extractor-batch-btn');
			const batchLimitEl = document.getElementById('limit');
			const batchForceEl = document.getElementById('force');
			const batchLog = document.getElementById('job-extractor-batch-log');
			const batchStatus = document.getElementById('job-extractor-batch-status');

			batchBtn.addEventListener('click', async function () {
				const limit = Math.max(1, Math.min(25, parseInt(batchLimitEl.value, 10) || 5));
				const force = batchForceEl.checked;
				const source = force ? allIds : pendingIds;
				const ids = source.slice(0, limit);

				if (ids.length === 0) {
					batchStatus.textContent = i18n.noVacanciesToProcess;
					return;
				}

				batchBtn.disabled = true;
				batchLog.innerHTML = '';

				for (let i = 0; i < ids.length; i++) {
					const id = ids[i];
					batchStatus.textContent = i18n.processingStatus
						.replace('%1$s', String(i + 1))
						.replace('%2$s', String(ids.length));

					const row = document.createElement('div');
					row.className = 'wp-ai-assistant-indexed-item';
					row.style.marginBottom = '10px';
					row.innerHTML = '<div class="indexed-header"><span class="indexed-id">#' + id + '</span></div>';
					batchLog.appendChild(row);

					const outcome = await extractOne(id, force);
					if (outcome.ok) {
						row.innerHTML = '<div class="indexed-header"><span class="indexed-id">#' + id + ' — ' + escapeHtml(outcome.payload.title) + '</span></div>' +
							'<pre class="job-extractor-pre">' + escapeHtml(JSON.stringify(outcome.payload.result, null, 2)) + '</pre>';
						updateStats(outcome.payload.stats);
					} else {
						row.innerHTML = '<div class="indexed-header"><span class="indexed-id">#' + id + '</span></div>' + errorBox(outcome.message);
						updateStats(outcome.stats);
					}
				}

				batchStatus.textContent = i18n.doneStatus.replace('%1$s', String(ids.length));
				batchBtn.disabled = false;
			});
		})();
		</script>
		<?php
	}

	/**
	 * Inline styles (mirrors RAGTestPage conventions)
	 */
	private static function enqueueStyles(): void
	{
		?>
		<style>
		.wp-ai-assistant-wrap { max-width: 1500px; }
		.wp-ai-assistant-header { margin: 20px 0; }
		.wp-ai-assistant-title { display: flex; align-items: center; gap: 8px; }
		.wp-ai-assistant-description { color: #646970; }
		.wp-ai-assistant-stats { display: flex; gap: 15px; margin-bottom: 25px; }
		.wp-ai-assistant-stat-card {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 15px 20px;
			text-align: center;
			flex: 1;
		}
		.stat-number { font-size: 28px; font-weight: 600; color: #2271b1; }
		.stat-label { color: #646970; font-size: 13px; margin-top: 5px; }
		.wp-ai-assistant-test-container {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 20px;
			margin-bottom: 20px;
		}
		.wp-ai-assistant-form-group { margin-bottom: 15px; }
		.wp-ai-assistant-label { display: block; margin-bottom: 5px; font-weight: 600; }
		.wp-ai-assistant-input { width: 100%; max-width: 400px; }
		.wp-ai-assistant-indexed-item {
			background: #f6f7f7;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 15px;
		}
		.indexed-header { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 12px; }
		.indexed-id { color: #646970; }

		/* Clearly-distinct error state — not just muted/gray text */
		.job-extractor-error-box {
			background: #fcf0f1;
			border-left: 4px solid #d63638;
			color: #451a1c;
			padding: 10px 12px;
			border-radius: 2px;
			font-size: 13px;
		}

		/* Side-by-side compare */
		.job-extractor-compare {
			display: flex;
			gap: 20px;
			align-items: flex-start;
		}
		.job-extractor-col { flex: 1; min-width: 0; }
		.job-extractor-pre {
			white-space: pre-wrap;
			background: #f6f7f7;
			border: 1px solid #c3c4c7;
			padding: 12px;
			border-radius: 4px;
			max-height: 650px;
			overflow-y: auto;
			font-size: 12px;
		}
		.job-extractor-original {
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 15px;
			background: #fafafa;
			min-height: 60px;
		}
		.job-extractor-original-title { margin: 0 0 6px; display: flex; justify-content: space-between; align-items: center; }
		.job-extractor-original-meta { font-size: 12px; color: #646970; margin: 0 0 10px; word-break: break-all; }
		.job-extractor-original-content {
			max-height: 560px;
			overflow-y: auto;
			font-size: 13px;
			line-height: 1.6;
			border-top: 1px solid #dcdcde;
			padding-top: 10px;
		}
		.job-extractor-original-content p { margin: 0 0 10px; }
		@media (max-width: 960px) {
			.job-extractor-compare { flex-direction: column; }
		}
		</style>
		<?php
	}
}

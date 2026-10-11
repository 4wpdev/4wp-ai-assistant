<?php
/**
 * Skill Stats Page
 *
 * Group 3: frequency of each skill/requirement/recommendation/methodology
 * across all extracted vacancies. Reads the persisted SkillDictionary
 * (forwp_skills + forwp_skill_aliases) via canonical_skill_id — a plain SQL
 * GROUP BY, not live PHP re-normalization. Also shows which raw variants
 * were folded into each canonical term, so the dictionary can be extended
 * (new aliases) from what's actually seen.
 *
 * @package ForWP\AIAssistant\Admin
 */

namespace ForWP\AIAssistant\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\AIAssistant\RAG\JobExtractor;
use ForWP\AIAssistant\RAG\JobSkillsInstaller;
use ForWP\AIAssistant\RAG\SkillDictionary;

class SkillStatsPage
{
	/**
	 * Render admin page
	 */
	public static function render(): void
	{
		global $wpdb;
		JobSkillsInstaller::maybeInstall();

		// Any row extracted before the dictionary existed (or added since
		// the last request) gets a canonical_skill_id here, so the GROUP BY
		// below never has to fall back to raw-string grouping.
		SkillDictionary::backfill();

		$skills_table = JobSkillsInstaller::skillsTable();
		$dictionary_table = JobSkillsInstaller::skillDictionaryTable();

		// One row per (field, canonical term): how many distinct vacancies
		// mention it. This is the plain GROUP BY the dictionary exists for.
		// phpcs:ignore -- no user input in this query
		$counts = $wpdb->get_results(
			"SELECT s.field_type, s.canonical_skill_id, d.canonical_name, COUNT(DISTINCT s.job_id) AS vacancies
			FROM {$skills_table} s
			LEFT JOIN {$dictionary_table} d ON d.id = s.canonical_skill_id
			GROUP BY s.field_type, s.canonical_skill_id
			ORDER BY vacancies DESC"
		);

		// One row per (field, canonical term, raw value): what got folded
		// together, for the "Raw variants seen" column.
		// phpcs:ignore -- no user input in this query
		$variant_rows = $wpdb->get_results(
			"SELECT field_type, canonical_skill_id, value, COUNT(*) AS cnt
			FROM {$skills_table}
			GROUP BY field_type, canonical_skill_id, value"
		);

		$variants_by_key = [];
		foreach ($variant_rows as $row) {
			$key = $row->field_type . '|' . $row->canonical_skill_id;
			$variants_by_key[$key][$row->value] = (int) $row->cnt;
		}

		$total_jobs = JobExtractor::getStats()['extracted'];

		// field_type => list of {canonical, vacancies, variants}
		$data = [];
		foreach ($counts as $row) {
			$key = $row->field_type . '|' . $row->canonical_skill_id;
			$data[$row->field_type][] = [
				'canonical' => $row->canonical_name ?? '',
				'vacancies' => (int) $row->vacancies,
				'variants' => $variants_by_key[$key] ?? [],
			];
		}

		self::enqueueStyles();
		?>
		<div class="wrap wp-ai-assistant-wrap">
			<div class="wp-ai-assistant-header">
				<h1 class="wp-ai-assistant-title">
					<span class="dashicons dashicons-chart-bar"></span>
					<?php esc_html_e('Skill Frequency', '4wp-ai-assistant'); ?>
				</h1>
				<p class="wp-ai-assistant-description">
					<?php esc_html_e('How often each term appears across all extracted vacancies (out of', '4wp-ai-assistant'); ?>
					<strong><?php echo esc_html($total_jobs); ?></strong>
					<?php esc_html_e('processed so far). Terms are folded together via the persisted skill dictionary (forwp_skills / forwp_skill_aliases), not recomputed on every page load. The "Raw variants seen" column shows exactly what got merged into each term.', '4wp-ai-assistant'); ?>
				</p>
			</div>

			<?php foreach (JobExtractor::FIELDS as $field): ?>
				<div class="wp-ai-assistant-test-container">
					<h2><?php echo esc_html(ucfirst($field)); ?></h2>

					<?php if (empty($data[$field])): ?>
						<p style="color:#646970;"><?php esc_html_e('No data yet.', '4wp-ai-assistant'); ?></p>
					<?php else: ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e('Term', '4wp-ai-assistant'); ?></th>
									<th style="width:110px;"><?php esc_html_e('Vacancies', '4wp-ai-assistant'); ?></th>
									<th style="width:80px;"><?php esc_html_e('Share', '4wp-ai-assistant'); ?></th>
									<th><?php esc_html_e('Raw variants seen', '4wp-ai-assistant'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($data[$field] as $item): ?>
									<?php
									$pct = $total_jobs > 0 ? round(($item['vacancies'] / $total_jobs) * 100) : 0;
									$variant_labels = [];
									foreach ($item['variants'] as $variant => $vcount) {
										$variant_labels[] = $vcount > 1 ? "{$variant} (×{$vcount})" : $variant;
									}
									?>
									<tr>
										<td><strong><?php echo esc_html($item['canonical']); ?></strong></td>
										<td><?php echo esc_html($item['vacancies']); ?> / <?php echo esc_html($total_jobs); ?></td>
										<td><?php echo esc_html($pct); ?>%</td>
										<td style="color:#646970;font-size:12px;">
											<?php echo esc_html(implode(', ', $variant_labels)); ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Inline styles
	 */
	private static function enqueueStyles(): void
	{
		?>
		<style>
		.wp-ai-assistant-wrap { max-width: 1500px; }
		.wp-ai-assistant-header { margin: 20px 0; }
		.wp-ai-assistant-title { display: flex; align-items: center; gap: 8px; }
		.wp-ai-assistant-description { color: #646970; }
		.wp-ai-assistant-test-container {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 20px;
			margin-bottom: 20px;
		}
		</style>
		<?php
	}
}

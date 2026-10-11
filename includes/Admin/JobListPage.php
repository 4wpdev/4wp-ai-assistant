<?php
/**
 * Job List Page
 *
 * Lists all job vacancies together with their extracted skills,
 * requirements, recommendations and methodology — read straight from the
 * job_skills table (no per-post JSON decoding). This is the first, plain
 * "see the data" view; group 3 (frequency stats) builds on the same table.
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

class JobListPage
{
	/**
	 * Vacancies per page
	 */
	const PER_PAGE = 20;

	/**
	 * Render admin page
	 */
	public static function render(): void
	{
		global $wpdb;
		JobSkillsInstaller::maybeInstall();

		$paged = max(1, (int) ($_GET['paged'] ?? 1));
		$offset = ($paged - 1) * self::PER_PAGE;

		$counts = wp_count_posts(JobExtractor::POST_TYPE);
		$total_jobs = (int) ($counts->publish ?? 0);

		$jobs = get_posts([
			'post_type' => JobExtractor::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => self::PER_PAGE,
			'offset' => $offset,
			'orderby' => 'ID',
			'order' => 'DESC',
		]);

		$job_ids = wp_list_pluck($jobs, 'ID');
		$rows_by_job = [];

		if (!empty($job_ids)) {
			$skills_table = JobSkillsInstaller::skillsTable();
			$placeholders = implode(',', array_fill(0, count($job_ids), '%d'));

			// phpcs:ignore -- placeholders are safely built from a fixed-size array of %d above
			$rows = $wpdb->get_results($wpdb->prepare(
				"SELECT job_id, field_type, value FROM {$skills_table} WHERE job_id IN ({$placeholders}) ORDER BY id ASC",
				$job_ids
			));

			foreach ($rows as $row) {
				$rows_by_job[$row->job_id][$row->field_type][] = $row->value;
			}
		}

		self::enqueueStyles();
		?>
		<div class="wrap wp-ai-assistant-wrap">
			<div class="wp-ai-assistant-header">
				<h1 class="wp-ai-assistant-title">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e('Extracted Vacancies', '4wp-ai-assistant'); ?>
				</h1>
				<p class="wp-ai-assistant-description">
					<?php esc_html_e('All job postings with their extracted skills, requirements, recommendations and methodology — read directly from the database.', '4wp-ai-assistant'); ?>
				</p>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:60px;"><?php esc_html_e('ID', '4wp-ai-assistant'); ?></th>
						<th style="width:220px;"><?php esc_html_e('Title', '4wp-ai-assistant'); ?></th>
						<th><?php esc_html_e('Skills', '4wp-ai-assistant'); ?></th>
						<th><?php esc_html_e('Requirements', '4wp-ai-assistant'); ?></th>
						<th><?php esc_html_e('Recommendations', '4wp-ai-assistant'); ?></th>
						<th><?php esc_html_e('Methodology', '4wp-ai-assistant'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($jobs)): ?>
						<tr>
							<td colspan="6"><?php esc_html_e('No vacancies found.', '4wp-ai-assistant'); ?></td>
						</tr>
					<?php endif; ?>
					<?php foreach ($jobs as $job): ?>
						<?php $data = $rows_by_job[$job->ID] ?? null; ?>
						<tr>
							<td>#<?php echo esc_html($job->ID); ?></td>
							<td>
								<strong>
									<a href="<?php echo esc_url(get_edit_post_link($job->ID)); ?>">
										<?php echo esc_html($job->post_title); ?>
									</a>
								</strong>
							</td>
							<?php if ($data === null): ?>
								<td colspan="4" style="color:#646970;"><?php esc_html_e('Not extracted yet.', '4wp-ai-assistant'); ?></td>
							<?php else: ?>
								<td><?php echo self::renderTags($data['skills'] ?? []); ?></td>
								<td><?php echo self::renderTags($data['requirements'] ?? []); ?></td>
								<td><?php echo self::renderTags($data['recommendations'] ?? []); ?></td>
								<td><?php echo self::renderTags($data['methodology'] ?? []); ?></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php self::renderPagination($paged, $total_jobs); ?>
		</div>
		<?php
	}

	/**
	 * Render a list of values as small pill tags, or a dash when empty.
	 *
	 * @param array $values
	 * @return string
	 */
	protected static function renderTags(array $values): string
	{
		if (empty($values)) {
			return '<span style="color:#a7aaad;">—</span>';
		}

		$out = '';
		foreach ($values as $value) {
			$out .= '<span class="job-extractor-tag">' . esc_html($value) . '</span>';
		}

		return $out;
	}

	/**
	 * Render simple pagination links.
	 *
	 * @param int $paged Current page
	 * @param int $total_jobs Total vacancy count
	 */
	protected static function renderPagination(int $paged, int $total_jobs): void
	{
		$total_pages = max(1, (int) ceil($total_jobs / self::PER_PAGE));

		if ($total_pages <= 1) {
			return;
		}
		?>
		<div class="tablenav" style="margin-top:15px;">
			<div class="tablenav-pages">
				<?php
				echo paginate_links([
					'base' => add_query_arg('paged', '%#%'),
					'format' => '',
					'current' => $paged,
					'total' => $total_pages,
				]);
				?>
			</div>
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
		.job-extractor-tag {
			display: inline-block;
			background: #f0f0f1;
			border: 1px solid #dcdcde;
			border-radius: 3px;
			padding: 2px 8px;
			margin: 2px 4px 2px 0;
			font-size: 12px;
			white-space: nowrap;
		}
		</style>
		<?php
	}
}

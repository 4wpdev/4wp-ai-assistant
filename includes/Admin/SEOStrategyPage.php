<?php
/**
 * SEO Strategy Page
 *
 * Group 4, first pass: Content Gap report. Every dictionary term
 * (forwp_skills), ranked by market demand (distinct vacancies mentioning
 * it), with a manually-set content_status/content_url so we know which
 * high-demand terms 4wp.dev has no article/plugin-page/course for yet.
 * Auto-detecting content_status from indexed RAG content or the
 * 4wp-seo-helper focus-keyword/GSC data is a later improvement — this is
 * the manual version so the gap list exists today.
 *
 * @package ForWP\AIAssistant\Admin
 */

namespace ForWP\AIAssistant\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\AIAssistant\RAG\JobSkillsInstaller;

class SEOStrategyPage
{
	const STATUSES = ['missing', 'planned', 'published'];

	/**
	 * Register AJAX handlers
	 */
	public static function initAjax(): void
	{
		add_action('wp_ajax_seo_strategy_update_status', [self::class, 'ajaxUpdateStatus']);
	}

	/**
	 * AJAX: update a dictionary term's content_status / content_url
	 */
	public static function ajaxUpdateStatus(): void
	{
		if (!check_ajax_referer('seo_strategy_ajax', 'nonce', false)) {
			wp_send_json_error(['message' => __('Security check failed. Please reload the page.', '4wp-ai-assistant')], 403);
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('You do not have permission to do this.', '4wp-ai-assistant')], 403);
		}

		$skill_id = isset($_POST['skill_id']) ? (int) $_POST['skill_id'] : 0;
		$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
		$url = isset($_POST['content_url']) ? esc_url_raw(wp_unslash($_POST['content_url'])) : '';

		if ($skill_id <= 0) {
			wp_send_json_error(['message' => __('Invalid term.', '4wp-ai-assistant')]);
		}

		if (!in_array($status, self::STATUSES, true)) {
			wp_send_json_error(['message' => __('Invalid status.', '4wp-ai-assistant')]);
		}

		global $wpdb;
		$dictionary_table = JobSkillsInstaller::skillDictionaryTable();

		$updated = $wpdb->update(
			$dictionary_table,
			['content_status' => $status, 'content_url' => $url !== '' ? $url : null],
			['id' => $skill_id],
			['%s', '%s'],
			['%d']
		);

		if ($updated === false) {
			wp_send_json_error(['message' => __('Database error while saving.', '4wp-ai-assistant')]);
		}

		wp_send_json_success(['skill_id' => $skill_id, 'status' => $status, 'content_url' => $url]);
	}

	/**
	 * Render admin page
	 */
	public static function render(): void
	{
		global $wpdb;
		JobSkillsInstaller::maybeInstall();

		$skills_table = JobSkillsInstaller::skillsTable();
		$dictionary_table = JobSkillsInstaller::skillDictionaryTable();

		// phpcs:ignore -- no user input in this query
		$rows = $wpdb->get_results(
			"SELECT d.id, d.canonical_name, d.content_status, d.content_url,
				COUNT(DISTINCT s.job_id) AS vacancies,
				GROUP_CONCAT(DISTINCT s.field_type ORDER BY s.field_type SEPARATOR ', ') AS field_types
			FROM {$dictionary_table} d
			INNER JOIN {$skills_table} s ON s.canonical_skill_id = d.id
			GROUP BY d.id
			ORDER BY vacancies DESC, d.canonical_name ASC"
		);

		$strings = [
			'savedStatus' => __('Saved.', '4wp-ai-assistant'),
			'errorStatus' => __('Could not save: %s', '4wp-ai-assistant'),
			'networkError' => __('Network error while saving.', '4wp-ai-assistant'),
			'saveLabel' => __('Save', '4wp-ai-assistant'),
			'savingLabel' => __('Saving…', '4wp-ai-assistant'),
		];

		self::enqueueStyles();
		?>
		<div class="wrap wp-ai-assistant-wrap">
			<div class="wp-ai-assistant-header">
				<h1 class="wp-ai-assistant-title">
					<span class="dashicons dashicons-chart-line"></span>
					<?php esc_html_e('SEO Strategy — Content Gap', '4wp-ai-assistant'); ?>
				</h1>
				<p class="wp-ai-assistant-description">
					<?php esc_html_e('Every term ranked by how many vacancies mention it. Mark whether 4wp.dev already has content for it — the highest-demand rows still marked "Missing" are the SEO/training-content backlog.', '4wp-ai-assistant'); ?>
				</p>
			</div>

			<?php if (empty($rows)): ?>
				<p style="color:#646970;"><?php esc_html_e('No data yet — extract some vacancies first.', '4wp-ai-assistant'); ?></p>
			<?php else: ?>
				<table class="wp-list-table widefat fixed striped seo-strategy-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Term', '4wp-ai-assistant'); ?></th>
							<th style="width:110px;"><?php esc_html_e('Vacancies', '4wp-ai-assistant'); ?></th>
							<th style="width:220px;"><?php esc_html_e('Appears as', '4wp-ai-assistant'); ?></th>
							<th style="width:150px;"><?php esc_html_e('Content status', '4wp-ai-assistant'); ?></th>
							<th><?php esc_html_e('Content URL', '4wp-ai-assistant'); ?></th>
							<th style="width:90px;"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rows as $row): ?>
							<tr data-skill-id="<?php echo esc_attr($row->id); ?>" class="seo-strategy-row status-<?php echo esc_attr($row->content_status); ?>">
								<td><strong><?php echo esc_html($row->canonical_name); ?></strong></td>
								<td><?php echo esc_html($row->vacancies); ?></td>
								<td style="color:#646970;font-size:12px;"><?php echo esc_html($row->field_types); ?></td>
								<td>
									<select class="seo-strategy-status">
										<?php foreach (self::STATUSES as $status): ?>
											<option value="<?php echo esc_attr($status); ?>" <?php selected($row->content_status, $status); ?>>
												<?php echo esc_html(ucfirst($status)); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<input type="url" class="regular-text seo-strategy-url" value="<?php echo esc_attr((string) $row->content_url); ?>" placeholder="https://4wp.dev/..." />
								</td>
								<td>
									<button type="button" class="button seo-strategy-save"><?php esc_html_e('Save', '4wp-ai-assistant'); ?></button>
									<span class="seo-strategy-row-msg"></span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
			var nonce = <?php echo wp_json_encode(wp_create_nonce('seo_strategy_ajax')); ?>;
			var i18n = <?php echo wp_json_encode($strings); ?>;

			document.querySelectorAll('.seo-strategy-save').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var row = btn.closest('tr');
					var skillId = row.getAttribute('data-skill-id');
					var status = row.querySelector('.seo-strategy-status').value;
					var contentUrl = row.querySelector('.seo-strategy-url').value;
					var msg = row.querySelector('.seo-strategy-row-msg');

					btn.disabled = true;
					msg.textContent = i18n.savingLabel;
					msg.className = 'seo-strategy-row-msg';

					var body = new URLSearchParams();
					body.set('action', 'seo_strategy_update_status');
					body.set('nonce', nonce);
					body.set('skill_id', skillId);
					body.set('status', status);
					body.set('content_url', contentUrl);

					fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
						.then(function (r) { return r.json(); })
						.then(function (data) {
							btn.disabled = false;
							if (data && data.success) {
								msg.textContent = i18n.savedStatus;
								msg.className = 'seo-strategy-row-msg seo-strategy-ok';
								row.className = 'seo-strategy-row status-' + status;
							} else {
								var m = (data && data.data && data.data.message) ? data.data.message : '';
								msg.textContent = i18n.errorStatus.replace('%s', m);
								msg.className = 'seo-strategy-row-msg seo-strategy-error';
							}
						})
						.catch(function () {
							btn.disabled = false;
							msg.textContent = i18n.networkError;
							msg.className = 'seo-strategy-row-msg seo-strategy-error';
						});
				});
			});
		})();
		</script>
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
		.seo-strategy-table td { vertical-align: middle; }
		.seo-strategy-row.status-published { background: #f0f6e8; }
		.seo-strategy-row.status-planned { background: #fcf9e8; }
		.seo-strategy-row-msg { margin-left: 8px; font-size: 12px; }
		.seo-strategy-row-msg.seo-strategy-ok { color: #2a6a1f; }
		.seo-strategy-row-msg.seo-strategy-error { color: #d63638; font-weight: 600; }
		</style>
		<?php
	}
}

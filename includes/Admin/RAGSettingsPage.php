<?php
/**
 * RAG Settings Page
 *
 * Settings for RAG indexing
 *
 * @package ForWP\AIAssistant\Admin
 */

namespace ForWP\AIAssistant\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\AIAssistant\RAG\IndexSettings;

class RAGSettingsPage
{
	/**
	 * Render settings page
	 */
	public static function render(): void
	{
		// Handle form submission
		if (isset($_POST['save_rag_settings']) && check_admin_referer('rag_settings')) {
			$settings = [
				'excluded_post_types' => array_map('sanitize_text_field', $_POST['excluded_post_types'] ?? []),
				'excluded_post_statuses' => array_map('sanitize_text_field', $_POST['excluded_post_statuses'] ?? []),
				'excluded_post_ids' => array_map('intval', explode(',', sanitize_text_field($_POST['excluded_post_ids'] ?? ''))),
				'min_content_length' => (int) ($_POST['min_content_length'] ?? 50),
				'index_excerpts' => isset($_POST['index_excerpts']),
			];

			IndexSettings::saveSettings($settings);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved!', '4wp-ai-assistant') . '</p></div>';
		}

		$settings = IndexSettings::getSettings();
		$available_post_types = IndexSettings::getAvailablePostTypes();
		$available_statuses = get_post_stati(['public' => false], 'objects');

		self::enqueueStyles();
		?>
		<div class="wrap wp-ai-assistant-wrap">
			<div class="wp-ai-assistant-header">
				<h1 class="wp-ai-assistant-title">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e('RAG Index Settings', '4wp-ai-assistant'); ?>
				</h1>
				<p class="wp-ai-assistant-description">
					<?php esc_html_e('Configure what content should be indexed for RAG search', '4wp-ai-assistant'); ?>
				</p>
			</div>

			<form method="post" action="" class="wp-ai-assistant-form">
				<?php wp_nonce_field('rag_settings'); ?>

				<div class="wp-ai-assistant-settings-section">
					<h2><?php esc_html_e('Excluded Post Types', '4wp-ai-assistant'); ?></h2>
					<p class="description">
						<?php esc_html_e('Select post types to exclude from indexing', '4wp-ai-assistant'); ?>
					</p>
					<div class="wp-ai-assistant-checkboxes">
						<?php foreach ($available_post_types as $type => $label): ?>
							<label class="wp-ai-assistant-checkbox">
								<input 
									type="checkbox" 
									name="excluded_post_types[]" 
									value="<?php echo esc_attr($type); ?>"
									<?php checked(in_array($type, $settings['excluded_post_types'], true)); ?>
								>
								<strong><?php echo esc_html($label); ?></strong>
								<code><?php echo esc_html($type); ?></code>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wp-ai-assistant-settings-section">
					<h2><?php esc_html_e('Excluded Post Statuses', '4wp-ai-assistant'); ?></h2>
					<p class="description">
						<?php esc_html_e('Post statuses to exclude (draft, private, etc.)', '4wp-ai-assistant'); ?>
					</p>
					<div class="wp-ai-assistant-checkboxes">
						<?php foreach ($available_statuses as $status => $status_obj): ?>
							<label class="wp-ai-assistant-checkbox">
								<input 
									type="checkbox" 
									name="excluded_post_statuses[]" 
									value="<?php echo esc_attr($status); ?>"
									<?php checked(in_array($status, $settings['excluded_post_statuses'], true)); ?>
								>
								<strong><?php echo esc_html($status_obj->label); ?></strong>
								<code><?php echo esc_html($status); ?></code>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wp-ai-assistant-settings-section">
					<h2><?php esc_html_e('Excluded Post IDs', '4wp-ai-assistant'); ?></h2>
					<p class="description">
						<?php esc_html_e('Comma-separated list of post IDs to exclude (e.g., 1, 5, 10)', '4wp-ai-assistant'); ?>
					</p>
					<input 
						type="text" 
						name="excluded_post_ids" 
						class="wp-ai-assistant-input" 
						value="<?php echo esc_attr(implode(', ', $settings['excluded_post_ids'])); ?>"
						placeholder="1, 5, 10"
					>
				</div>

				<div class="wp-ai-assistant-settings-section">
					<h2><?php esc_html_e('Content Filtering', '4wp-ai-assistant'); ?></h2>
					
					<label class="wp-ai-assistant-field">
						<span class="field-label"><?php esc_html_e('Minimum Content Length', '4wp-ai-assistant'); ?></span>
						<input 
							type="number" 
							name="min_content_length" 
							class="wp-ai-assistant-input" 
							value="<?php echo esc_attr($settings['min_content_length']); ?>"
							min="0"
						>
						<span class="field-description">
							<?php esc_html_e('Minimum number of characters required to index a post', '4wp-ai-assistant'); ?>
						</span>
					</label>

					<label class="wp-ai-assistant-checkbox">
						<input 
							type="checkbox" 
							name="index_excerpts" 
							value="1"
							<?php checked($settings['index_excerpts']); ?>
						>
						<strong><?php esc_html_e('Index Post Excerpts', '4wp-ai-assistant'); ?></strong>
						<span class="field-description">
							<?php esc_html_e('Include post excerpts in search results', '4wp-ai-assistant'); ?>
						</span>
					</label>
				</div>

				<div class="wp-ai-assistant-form-actions">
					<button type="submit" name="save_rag_settings" class="wp-ai-assistant-button wp-ai-assistant-button-primary">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e('Save Settings', '4wp-ai-assistant'); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue styles
	 */
	private static function enqueueStyles(): void
	{
		?>
		<style>
		.wp-ai-assistant-settings-section {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
			padding: 20px;
			margin-bottom: 20px;
		}

		.wp-ai-assistant-settings-section h2 {
			margin-top: 0;
			border-bottom: 1px solid #c3c4c7;
			padding-bottom: 10px;
		}

		.wp-ai-assistant-checkboxes {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
			gap: 15px;
			margin-top: 15px;
		}

		.wp-ai-assistant-checkbox {
			display: flex;
			align-items: center;
			gap: 10px;
			padding: 10px;
			background: #f6f7f7;
			border-radius: 4px;
			cursor: pointer;
		}

		.wp-ai-assistant-checkbox input[type="checkbox"] {
			margin: 0;
		}

		.wp-ai-assistant-checkbox code {
			font-size: 11px;
			background: #fff;
			padding: 2px 6px;
			border-radius: 3px;
		}

		.wp-ai-assistant-field {
			display: flex;
			flex-direction: column;
			gap: 5px;
			margin-bottom: 20px;
		}

		.field-label {
			font-weight: 600;
			color: #1d2327;
		}

		.field-description {
			font-size: 13px;
			color: #646970;
			font-style: italic;
		}
		</style>
		<?php
	}
}


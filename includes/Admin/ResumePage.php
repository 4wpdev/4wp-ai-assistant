<?php
/**
 * Resume Page
 *
 * Group 5 (resume generator) — placeholder. On hold per explicit request;
 * the menu entry exists so the roadmap stays visible in wp-admin.
 *
 * @package ForWP\AIAssistant\Admin
 */

namespace ForWP\AIAssistant\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class ResumePage
{
	/**
	 * Render admin page
	 */
	public static function render(): void
	{
		?>
		<div class="wrap wp-ai-assistant-wrap">
			<h1>
				<span class="dashicons dashicons-id"></span>
				<?php esc_html_e('Resume Generator', '4wp-ai-assistant'); ?>
			</h1>
			<div class="notice notice-info" style="padding: 16px;">
				<p><strong><?php esc_html_e('Coming soon.', '4wp-ai-assistant'); ?></strong></p>
				<p>
					<?php esc_html_e('Will match a candidate\'s resume against the skill dictionary and market-demand stats from the SEO Strategy page, to score fit and suggest what to add. On hold until the Content Gap workflow is settled.', '4wp-ai-assistant'); ?>
				</p>
			</div>
		</div>
		<?php
	}
}

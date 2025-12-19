<?php
/**
 * Extension class
 *
 * Checks requirements and compatibility
 *
 * @package ForWP\AIAssistant\Core
 */

namespace ForWP\AIAssistant\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class Extension
{
	/**
	 * Check if all requirements are met
	 *
	 * @return bool
	 */
	public static function checkRequirements(): bool
	{
		// Check PHP version
		if (version_compare(PHP_VERSION, '8.0', '<')) {
			add_action('admin_notices', function () {
				echo '<div class="notice notice-error"><p>';
				printf(
					esc_html__('4WP AI Assistant requires PHP 8.0 or higher. You are running PHP %s.', '4wp-ai-assistant'),
					esc_html(PHP_VERSION)
				);
				echo '</p></div>';
			});
			return false;
		}

		// Check WordPress version
		if (version_compare(get_bloginfo('version'), '6.0', '<')) {
			add_action('admin_notices', function () {
				echo '<div class="notice notice-error"><p>';
				printf(
					esc_html__('4WP AI Assistant requires WordPress 6.0 or higher. You are running WordPress %s.', '4wp-ai-assistant'),
					esc_html(get_bloginfo('version'))
				);
				echo '</p></div>';
			});
			return false;
		}

		return true;
	}
}
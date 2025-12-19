<?php
/**
 * Extension class
 *
 * Checks requirements and compatibility
 *
 * @package ForWP\LMS\AI\Core
 */

namespace ForWP\LMS\AI\Core;

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
					esc_html__('LMS4WP AI requires PHP 8.0 or higher. You are running PHP %s.', 'lms4wp-ai'),
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
					esc_html__('LMS4WP AI requires WordPress 6.0 or higher. You are running WordPress %s.', 'lms4wp-ai'),
					esc_html(get_bloginfo('version'))
				);
				echo '</p></div>';
			});
			return false;
		}

		return true;
	}
}
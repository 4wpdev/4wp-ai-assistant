<?php
/**
 * Admin Menu
 *
 * @package ForWP\LMS\AI\Admin
 */

namespace ForWP\LMS\AI\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class Menu
{
	/**
	 * Initialize admin menu
	 */
	public static function init(): void
	{
		$self = new self();
		add_action('admin_menu', [$self, 'addMenu']);
	}

	/**
	 * Add admin menu
	 */
	public function addMenu(): void
	{
		// Check if parent menu exists
		if (!menu_page_url('lms4wp', false)) {
			// If parent menu doesn't exist, create our own top-level menu
			add_menu_page(
				__('LMS4WP AI', 'lms4wp-ai'),
				__('LMS4WP AI', 'lms4wp-ai'),
				'manage_options',
				'lms4wp-ai',
				[TestPage::class, 'render'],
				'dashicons-robot',
				26.5
			);
		} else {
			// Add as submenu to LMS4WP
			add_submenu_page(
				'lms4wp', // Parent menu (LMS4WP)
				__('AI Assistant', 'lms4wp-ai'),
				__('AI Assistant', 'lms4wp-ai'),
				'manage_options',
				'lms4wp-ai',
				[TestPage::class, 'render']
			);
		}
	}
}
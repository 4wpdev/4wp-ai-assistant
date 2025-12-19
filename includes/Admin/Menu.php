<?php
/**
 * Admin Menu
 *
 * @package ForWP\AIAssistant\Admin
 */

namespace ForWP\AIAssistant\Admin;

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
		// Check if LMS4WP parent menu exists (optional integration)
		if (function_exists('wp_ai_assistant_has_lms4wp') && wp_ai_assistant_has_lms4wp() && menu_page_url('lms4wp', false)) {
			// Add as submenu to LMS4WP if it exists
			$parent_slug = 'lms4wp';
			$main_slug = '4wp-ai-assistant';
		} else {
			// Create our own top-level menu
			$parent_slug = '4wp-ai-assistant';
			$main_slug = '4wp-ai-assistant';
			
			add_menu_page(
				__('4WP AI Assistant', '4wp-ai-assistant'),
				__('AI Assistant', '4wp-ai-assistant'),
				'manage_options',
				$parent_slug,
				[TestPage::class, 'render'],
				'dashicons-robot',
				26.5
			);
		}

		// Main test page
		add_submenu_page(
			$parent_slug,
			__('AI Chat', '4wp-ai-assistant'),
			__('AI Chat', '4wp-ai-assistant'),
			'manage_options',
			$main_slug,
			[TestPage::class, 'render']
		);

		// RAG Test page
		add_submenu_page(
			$parent_slug,
			__('RAG Engine', '4wp-ai-assistant'),
			__('RAG Engine', '4wp-ai-assistant'),
			'manage_options',
			'4wp-ai-assistant-rag',
			[RAGTestPage::class, 'render']
		);

		// RAG Settings page
		add_submenu_page(
			$parent_slug,
			__('RAG Settings', '4wp-ai-assistant'),
			__('RAG Settings', '4wp-ai-assistant'),
			'manage_options',
			'4wp-ai-assistant-rag-settings',
			[RAGSettingsPage::class, 'render']
		);
	}
}
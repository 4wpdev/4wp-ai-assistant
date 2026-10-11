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
				'dashicons-format-chat',
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

		// Job Extractor page
		add_submenu_page(
			$parent_slug,
			__('Job Extractor', '4wp-ai-assistant'),
			__('Job Extractor', '4wp-ai-assistant'),
			'manage_options',
			'4wp-ai-assistant-job-extractor',
			[JobExtractorPage::class, 'render']
		);

		// Job List page
		add_submenu_page(
			$parent_slug,
			__('Extracted Vacancies', '4wp-ai-assistant'),
			__('Extracted Vacancies', '4wp-ai-assistant'),
			'manage_options',
			'4wp-ai-assistant-job-list',
			[JobListPage::class, 'render']
		);

		// Skill Stats page
		add_submenu_page(
			$parent_slug,
			__('Skill Frequency', '4wp-ai-assistant'),
			__('Skill Frequency', '4wp-ai-assistant'),
			'manage_options',
			'4wp-ai-assistant-skill-stats',
			[SkillStatsPage::class, 'render']
		);

		// SEO Strategy page (Content Gap report)
		add_submenu_page(
			$parent_slug,
			__('SEO Strategy', '4wp-ai-assistant'),
			__('SEO Strategy', '4wp-ai-assistant'),
			'manage_options',
			'4wp-ai-assistant-seo-strategy',
			[SEOStrategyPage::class, 'render']
		);

		// Resume Generator page (placeholder — on hold)
		add_submenu_page(
			$parent_slug,
			__('Resume — Coming Soon', '4wp-ai-assistant'),
			__('Resume', '4wp-ai-assistant'),
			'manage_options',
			'4wp-ai-assistant-resume',
			[ResumePage::class, 'render']
		);

		// Roadmap page
		add_submenu_page(
			$parent_slug,
			__('Roadmap', '4wp-ai-assistant'),
			__('Roadmap', '4wp-ai-assistant'),
			'manage_options',
			'4wp-ai-assistant-roadmap',
			[RoadmapPage::class, 'render']
		);
	}
}

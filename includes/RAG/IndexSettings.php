<?php
/**
 * RAG Index Settings
 *
 * Manages settings for content indexing
 *
 * @package ForWP\AI\RAG
 */

namespace ForWP\AI\RAG;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class IndexSettings
{
	/**
	 * Option name for settings
	 */
	const OPTION_NAME = '4wp_ai_rag_index_settings';

	/**
	 * Default settings
	 *
	 * @return array
	 */
	public static function getDefaults(): array
	{
		return [
			'excluded_post_types' => ['attachment', 'revision', 'nav_menu_item'],
			'excluded_post_statuses' => ['draft', 'private', 'trash', 'auto-draft'],
			'excluded_post_ids' => [],
			'min_content_length' => 50, // Minimum characters to index
			'index_excerpts' => true,
		];
	}

	/**
	 * Get current settings
	 *
	 * @return array
	 */
	public static function getSettings(): array
	{
		$defaults = self::getDefaults();
		$saved = get_option(self::OPTION_NAME, []);
		return wp_parse_args($saved, $defaults);
	}

	/**
	 * Save settings
	 *
	 * @param array $settings Settings to save
	 * @return bool
	 */
	public static function saveSettings(array $settings): bool
	{
		$defaults = self::getDefaults();
		$settings = wp_parse_args($settings, $defaults);
		return update_option(self::OPTION_NAME, $settings);
	}

	/**
	 * Check if post type should be excluded
	 *
	 * @param string $post_type Post type
	 * @return bool
	 */
	public static function isPostTypeExcluded(string $post_type): bool
	{
		$settings = self::getSettings();
		return in_array($post_type, $settings['excluded_post_types'], true);
	}

	/**
	 * Check if post status should be excluded
	 *
	 * @param string $post_status Post status
	 * @return bool
	 */
	public static function isPostStatusExcluded(string $post_status): bool
	{
		$settings = self::getSettings();
		return in_array($post_status, $settings['excluded_post_statuses'], true);
	}

	/**
	 * Check if post ID should be excluded
	 *
	 * @param int $post_id Post ID
	 * @return bool
	 */
	public static function isPostIdExcluded(int $post_id): bool
	{
		$settings = self::getSettings();
		return in_array($post_id, $settings['excluded_post_ids'], true);
	}

	/**
	 * Check if content is too short
	 *
	 * @param string $content Content to check
	 * @return bool
	 */
	public static function isContentTooShort(string $content): bool
	{
		$settings = self::getSettings();
		$min_length = (int) $settings['min_content_length'];
		return mb_strlen(wp_strip_all_tags($content)) < $min_length;
	}

	/**
	 * Get available post types for indexing
	 *
	 * @return array
	 */
	public static function getAvailablePostTypes(): array
	{
		$post_types = get_post_types(['public' => true], 'objects');
		$available = [];

		foreach ($post_types as $post_type) {
			$available[$post_type->name] = $post_type->label;
		}

		return $available;
	}
}


<?php
/**
 * Content Indexer
 *
 * Indexes WordPress content for RAG search
 *
 * @package ForWP\AIAssistant\RAG
 */

namespace ForWP\AIAssistant\RAG;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class ContentIndexer
{
	/**
	 * Index WordPress content
	 *
	 * @return array Indexed content
	 */
	public static function indexContent(): array
	{
		$content = [];
		$settings = IndexSettings::getSettings();

		// Get all public post types (excluding configured exclusions)
		$post_types = get_post_types(['public' => true], 'names');
		$post_types = array_filter($post_types, function ($type) {
			return !IndexSettings::isPostTypeExcluded($type);
		});

		if (empty($post_types)) {
			return $content;
		}

		// Get all published posts
		$posts = get_posts([
			'post_type' => array_values($post_types),
			'post_status' => 'publish',
			'numberposts' => -1,
			'post__not_in' => $settings['excluded_post_ids'],
		]);

		foreach ($posts as $post) {
			// Skip if excluded
			if (IndexSettings::isPostIdExcluded($post->ID)) {
				continue;
			}

			// Get clean content
			$clean_content = wp_strip_all_tags($post->post_content);

			// Skip if content is too short
			if (IndexSettings::isContentTooShort($clean_content)) {
				continue;
			}

			$item = [
				'id' => $post->ID,
				'type' => $post->post_type,
				'title' => $post->post_title,
				'content' => $clean_content,
				'url' => get_permalink($post->ID),
			];

			// Add excerpt if enabled
			if ($settings['index_excerpts'] && !empty($post->post_excerpt)) {
				$item['excerpt'] = $post->post_excerpt;
			}

			$content[] = $item;
		}

		return $content;
	}

	/**
	 * Get content by ID
	 *
	 * @param int $post_id Post ID
	 * @return array|null
	 */
	public static function getContent(int $post_id): ?array
	{
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'publish') {
			return null;
		}

		return [
			'id' => $post->ID,
			'type' => $post->post_type,
			'title' => $post->post_title,
			'content' => wp_strip_all_tags($post->post_content),
			'excerpt' => $post->post_excerpt,
			'url' => get_permalink($post->ID),
		];
	}
}


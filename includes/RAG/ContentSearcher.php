<?php
/**
 * Content Searcher
 *
 * Searches indexed content for RAG
 *
 * @package ForWP\AI\RAG
 */

namespace ForWP\AI\RAG;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class ContentSearcher
{
	/**
	 * Search content by query
	 *
	 * @param string $query Search query
	 * @param int $limit Maximum results
	 * @return array Relevant content
	 */
	public static function search(string $query, int $limit = 5): array
	{
		$indexed = ContentIndexer::indexContent();
		$results = [];

		$query_lower = mb_strtolower($query);
		$query_words = explode(' ', $query_lower);

		foreach ($indexed as $item) {
			$score = 0;
			$text = mb_strtolower($item['title'] . ' ' . $item['content'] . ' ' . $item['excerpt']);

			// Simple relevance scoring
			foreach ($query_words as $word) {
				if (strlen($word) < 3) {
					continue;
				}

				// Title matches are more important
				if (strpos(mb_strtolower($item['title']), $word) !== false) {
					$score += 10;
				}

				// Content matches
				$count = substr_count($text, $word);
				$score += $count * 2;

				// Exact phrase match
				if (strpos($text, $query_lower) !== false) {
					$score += 20;
				}
			}

			if ($score > 0) {
				$item['relevance_score'] = $score;
				$results[] = $item;
			}
		}

		// Sort by relevance
		usort($results, function ($a, $b) {
			return $b['relevance_score'] <=> $a['relevance_score'];
		});

		// Return top results
		return array_slice($results, 0, $limit);
	}
}


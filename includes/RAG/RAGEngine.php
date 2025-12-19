<?php
/**
 * RAG Engine
 *
 * Retrieval Augmented Generation engine
 *
 * @package ForWP\AIAssistant\RAG
 */

namespace ForWP\AIAssistant\RAG;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class RAGEngine
{
	/**
	 * Initialize RAG Engine
	 */
	public static function init(): void
	{
		// Future: Add hooks for content indexing, caching, etc.
	}

	/**
	 * Get context for query
	 *
	 * @param string $query User query
	 * @return string Context string
	 */
	public static function getContext(string $query): string
	{
		$results = ContentSearcher::search($query, 3);

		if (empty($results)) {
			return '';
		}

		$context = "Relevant information from website:\n\n";

		foreach ($results as $result) {
			$context .= sprintf(
				"Title: %s\nURL: %s\nContent: %s\n\n",
				$result['title'],
				$result['url'],
				wp_trim_words($result['content'], 100)
			);
		}

		return $context;
	}

	/**
	 * Enhance message with RAG context
	 *
	 * @param string $message Original message
	 * @return string Enhanced message with context
	 */
	public static function enhanceMessage(string $message): string
	{
		$context = self::getContext($message);

		if (empty($context)) {
			return $message;
		}

		return $context . "\n\nBased on the above information, answer the following question:\n" . $message;
	}
}


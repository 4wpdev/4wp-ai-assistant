<?php
/**
 * AI Provider Interface
 *
 * @package ForWP\AI\AI
 */

namespace ForWP\AI\AI;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

interface ProviderInterface
{
	/**
	 * Send message to AI provider
	 *
	 * @param string $message User message
	 * @param array $options Additional options
	 * @return array|\WP_Error Response array or WP_Error on failure
	 */
	public function sendMessage(string $message, array $options = []): array|\WP_Error;

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Check if provider is configured
	 *
	 * @return bool
	 */
	public function isConfigured(): bool;

	/**
	 * Get available models
	 *
	 * @return array
	 */
	public function getAvailableModels(): array;
}




<?php
/**
 * Groq Provider
 *
 * @package ForWP\AI\AI\Providers
 */

namespace ForWP\AI\AI\Providers;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\AI\AI\AbstractProvider;
use ForWP\AI\Core\EnvLoader;

class GroqProvider extends AbstractProvider
{
	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'Groq';
	}

	/**
	 * Get API key
	 *
	 * @return string|null
	 */
	protected function getApiKey(): ?string
	{
		return EnvLoader::get('GROQ_API_KEY');
	}

	/**
	 * Get API URL
	 *
	 * @return string
	 */
	protected function getApiUrl(): string
	{
		return 'https://api.groq.com/openai/v1/chat/completions';
	}

	/**
	 * Get default model
	 *
	 * @return string
	 */
	protected function getDefaultModel(): string
	{
		return EnvLoader::get('GROQ_MODEL', 'llama-3.1-8b-instant');
	}

	/**
	 * Get available models
	 *
	 * @return array
	 */
	public function getAvailableModels(): array
	{
		return [
			'llama-3.1-8b-instant',
			'llama-3.1-70b-versatile',
			'llama-3.3-70b-versatile',
			'llama-3.1-405b-reasoning',
			'mixtral-8x7b-32768',
			'gemma-7b-it',
		];
	}

	/**
	 * Send message
	 *
	 * @param string $message User message
	 * @param array $options Additional options
	 * @return array|\WP_Error
	 */
	public function sendMessage(string $message, array $options = []): array|\WP_Error
	{
		$body = $this->buildRequestBody($message, $options);
		return $this->makeRequest($body);
	}
}
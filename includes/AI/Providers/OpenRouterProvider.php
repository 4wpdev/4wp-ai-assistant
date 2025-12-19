<?php
/**
 * OpenRouter Provider
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

class OpenRouterProvider extends AbstractProvider
{
	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'OpenRouter';
	}

	/**
	 * Get API key
	 *
	 * @return string|null
	 */
	protected function getApiKey(): ?string
	{
		return EnvLoader::get('OPENROUTER_API_KEY');
	}

	/**
	 * Get API URL
	 *
	 * @return string
	 */
	protected function getApiUrl(): string
	{
		return 'https://openrouter.ai/api/v1/chat/completions';
	}

	/**
	 * Get default model
	 *
	 * @return string
	 */
	protected function getDefaultModel(): string
	{
		return EnvLoader::get('OPENROUTER_MODEL', 'meta-llama/llama-3.1-8b-instruct');
	}

	/**
	 * Get available models
	 *
	 * @return array
	 */
	public function getAvailableModels(): array
	{
		return [
			'meta-llama/llama-3.1-8b-instruct',
			'meta-llama/llama-3.1-70b-instruct',
			'openai/gpt-4',
			'openai/gpt-3.5-turbo',
			'anthropic/claude-3-haiku',
			'anthropic/claude-3-sonnet',
		];
	}

	/**
	 * Build request headers
	 *
	 * @return array
	 */
	protected function getHeaders(): array
	{
		$api_key = $this->getApiKey();
		return [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type' => 'application/json',
			'HTTP-Referer' => home_url(), // Optional: for analytics
			'X-Title' => get_bloginfo('name'), // Optional: for analytics
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
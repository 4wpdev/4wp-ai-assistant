<?php
/**
 * Groq Provider
 *
 * @package ForWP\AIAssistant\AI\Providers
 */

namespace ForWP\AIAssistant\AI\Providers;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\AIAssistant\AI\AbstractProvider;
use ForWP\AIAssistant\Core\EnvLoader;

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
			'openai/gpt-oss-120b',
			'openai/gpt-oss-20b',
			'groq/compound',
			'groq/compound-mini',
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
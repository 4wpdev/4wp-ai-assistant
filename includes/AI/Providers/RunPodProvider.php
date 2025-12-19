<?php
/**
 * RunPod Provider
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

class RunPodProvider extends AbstractProvider
{
	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return 'RunPod';
	}

	/**
	 * Get API key
	 *
	 * @return string|null
	 */
	protected function getApiKey(): ?string
	{
		return EnvLoader::get('RUNPOD_API_KEY');
	}

	/**
	 * Get API URL
	 *
	 * @return string
	 */
	protected function getApiUrl(): string
	{
		$endpoint_id = EnvLoader::get('RUNPOD_ENDPOINT_ID');
		if (empty($endpoint_id)) {
			return '';
		}
		return sprintf('https://api.runpod.io/v2/%s/runsync', $endpoint_id);
	}

	/**
	 * Get default model
	 *
	 * @return string
	 */
	protected function getDefaultModel(): string
	{
		return EnvLoader::get('RUNPOD_MODEL', 'meta-llama/Llama-3.1-8B-Instruct');
	}

	/**
	 * Get available models
	 *
	 * @return array
	 */
	public function getAvailableModels(): array
	{
		return [
			'meta-llama/Llama-3.1-8B-Instruct',
			'meta-llama/Llama-3.1-70B-Instruct',
			'mistralai/Mistral-7B-Instruct-v0.2',
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
		];
	}

	/**
	 * Build request body for RunPod
	 *
	 * @param string $message User message
	 * @param array $options Additional options
	 * @return array
	 */
	protected function buildRequestBody(string $message, array $options = []): array
	{
		$model = $options['model'] ?? $this->getDefaultModel();
		$temperature = $options['temperature'] ?? $this->defaultOptions['temperature'];
		$max_tokens = $options['max_tokens'] ?? $this->defaultOptions['max_tokens'];

		// RunPod uses different format
		return [
			'input' => [
				'model' => $model,
				'messages' => [
					[
						'role' => 'user',
						'content' => $message
					]
				],
				'temperature' => $temperature,
				'max_tokens' => $max_tokens,
			]
		];
	}

	/**
	 * Parse RunPod response
	 *
	 * @param array $data Response data
	 * @param string $model Model name
	 * @return array
	 */
	protected function parseResponse(array $data, string $model): array
	{
		// RunPod response structure is different
		$output = $data['output'] ?? [];
		$message = $output['choices'][0]['message']['content'] ?? $output['text'] ?? '';

		return [
			'success' => true,
			'message' => $message,
			'usage' => $output['usage'] ?? [],
			'model' => $model,
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
		if (empty($this->getApiUrl())) {
			return new \WP_Error(
				'no_endpoint',
				__('RunPod endpoint ID is not configured. Please set RUNPOD_ENDPOINT_ID in .env file.', '4wp-ai-assistant')
			);
		}

		$body = $this->buildRequestBody($message, $options);
		return $this->makeRequest($body);
	}
}
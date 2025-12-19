<?php
/**
 * Abstract AI Provider
 *
 * Base class for all AI providers
 *
 * @package ForWP\AIAssistant\AI
 */

namespace ForWP\AIAssistant\AI;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

abstract class AbstractProvider implements ProviderInterface
{
	/**
	 * Default options
	 *
	 * @var array
	 */
	protected $defaultOptions = [
		'temperature' => 0.7,
		'max_tokens' => 1024,
	];

	/**
	 * Get API key from environment
	 *
	 * @return string|null
	 */
	abstract protected function getApiKey(): ?string;

	/**
	 * Get API endpoint URL
	 *
	 * @return string
	 */
	abstract protected function getApiUrl(): string;

	/**
	 * Get default model
	 *
	 * @return string
	 */
	abstract protected function getDefaultModel(): string;

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
	 * Build request body
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

		return [
			'model' => $model,
			'messages' => [
				[
					'role' => 'user',
					'content' => $message
				]
			],
			'temperature' => $temperature,
			'max_tokens' => $max_tokens,
		];
	}

	/**
	 * Make HTTP request
	 *
	 * @param array $body Request body
	 * @return array|\WP_Error
	 */
	protected function makeRequest(array $body): array|\WP_Error
	{
		$api_key = $this->getApiKey();

		if (empty($api_key)) {
			$provider_name = method_exists($this, 'getName') ? $this->getName() : 'AI Provider';
			return new \WP_Error(
				'no_api_key',
				sprintf(
					__('%s API key is not configured. Please check your .env file.', '4wp-ai-assistant'),
					$provider_name
				)
			);
		}

		$response = wp_remote_post(
			$this->getApiUrl(),
			[
				'headers' => $this->getHeaders(),
				'body' => json_encode($body),
				'timeout' => 30,
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		if ($response_code !== 200) {
			$error_data = json_decode($response_body, true);
			$provider_name = method_exists($this, 'getName') ? $this->getName() : 'AI Provider';
			return new \WP_Error(
				'api_error',
				$error_data['error']['message'] ?? sprintf(__('%s API error', '4wp-ai-assistant'), $provider_name),
				['status' => $response_code]
			);
		}

		return $this->parseResponse(json_decode($response_body, true), $body['model'] ?? $this->getDefaultModel());
	}

	/**
	 * Parse API response
	 *
	 * @param array $data Response data
	 * @param string $model Model name
	 * @return array
	 */
	protected function parseResponse(array $data, string $model): array
	{
		return [
			'success' => true,
			'message' => $data['choices'][0]['message']['content'] ?? '',
			'usage' => $data['usage'] ?? [],
			'model' => $data['model'] ?? $model,
		];
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool
	 */
	public function isConfigured(): bool
	{
		return !empty($this->getApiKey());
	}
}
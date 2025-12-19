<?php
/**
 * Provider Manager
 *
 * Manages AI providers and selects active one
 *
 * @package ForWP\AI\AI
 */

namespace ForWP\AI\AI;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\AI\AI\Providers\GroqProvider;
use ForWP\AI\AI\Providers\RunPodProvider;
use ForWP\AI\AI\Providers\OpenRouterProvider;
use ForWP\AI\Core\EnvLoader;

class ProviderManager
{
	/**
	 * Available providers
	 *
	 * @var array
	 */
	private static $providers = [];

	/**
	 * Get all available providers
	 *
	 * @return ProviderInterface[]
	 */
	public static function getProviders(): array
	{
		if (empty(self::$providers)) {
			self::$providers = [
				'groq' => new GroqProvider(),
				'runpod' => new RunPodProvider(),
				'openrouter' => new OpenRouterProvider(),
			];
		}

		return self::$providers;
	}

	/**
	 * Get provider by ID
	 *
	 * @param string $provider_id Provider ID
	 * @return ProviderInterface|null
	 */
	public static function getProvider(string $provider_id): ?ProviderInterface
	{
		$providers = self::getProviders();
		return $providers[$provider_id] ?? null;
	}

	/**
	 * Get active provider (from settings or default)
	 *
	 * @return ProviderInterface
	 */
	public static function getActiveProvider(): ProviderInterface
	{
		// Get from settings or use default
		$provider_id = get_option('lms4wp_ai_provider', 'groq');
		
		$provider = self::getProvider($provider_id);
		
		// Fallback to Groq if selected provider is not configured
		if (!$provider || !$provider->isConfigured()) {
			$provider = self::getProvider('groq');
		}

		return $provider;
	}

	/**
	 * Get configured providers
	 *
	 * @return array
	 */
	public static function getConfiguredProviders(): array
	{
		$configured = [];
		foreach (self::getProviders() as $id => $provider) {
			if ($provider->isConfigured()) {
				$configured[$id] = $provider;
			}
		}
		return $configured;
	}
}
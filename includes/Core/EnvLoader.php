<?php
/**
 * Environment Loader
 *
 * Loads .env file and provides access to environment variables
 *
 * @package ForWP\AI\Core
 */

namespace ForWP\AI\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class EnvLoader
{
	/**
	 * Whether .env file has been loaded
	 *
	 * @var bool
	 */
	private static $loaded = false;

	/**
	 * Load .env file
	 *
	 * @param string $file_path Path to .env file
	 */
	public static function load(string $file_path): void
	{
		if (self::$loaded || !file_exists($file_path)) {
			return;
		}

		$lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {
			// Skip comments
			if (strpos(trim($line), '#') === 0) {
				continue;
			}

			// Parse KEY="VALUE" or KEY=VALUE
			if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', $line, $matches)) {
				$key = $matches[1];
				$value = trim($matches[2]);

				// Remove quotes if present
				if (preg_match('/^"(.*)"$/', $value, $quote_matches)) {
					$value = $quote_matches[1];
				} elseif (preg_match("/^'(.*)'$/", $value, $quote_matches)) {
					$value = $quote_matches[1];
				}

				// Set environment variable
				putenv("$key=$value");
				$_ENV[$key] = $value;
			}
		}

		self::$loaded = true;
	}

	/**
	 * Get environment variable
	 *
	 * @param string $key Environment variable key
	 * @param mixed $default Default value if not found
	 * @return mixed
	 */
	public static function get(string $key, $default = null)
	{
		// Try getenv first
		$value = getenv($key);
		if ($value !== false) {
			return $value;
		}

		// Try $_ENV
		if (isset($_ENV[$key])) {
			return $_ENV[$key];
		}

		return $default;
	}

	// Convenience methods for each provider
	public static function getGroqApiKey(): ?string
	{
		return self::get('GROQ_API_KEY') ?: null;
	}

	public static function getGroqModel(): string
	{
		return self::get('GROQ_MODEL', 'llama-3.1-8b-instant');
	}

	public static function getRunPodApiKey(): ?string
	{
		return self::get('RUNPOD_API_KEY') ?: null;
	}

	public static function getRunPodEndpointId(): ?string
	{
		return self::get('RUNPOD_ENDPOINT_ID') ?: null;
	}

	public static function getRunPodModel(): string
	{
		return self::get('RUNPOD_MODEL', 'meta-llama/Llama-3.1-8B-Instruct');
	}

	public static function getOpenRouterApiKey(): ?string
	{
		return self::get('OPENROUTER_API_KEY') ?: null;
	}

	public static function getOpenRouterModel(): string
	{
		return self::get('OPENROUTER_MODEL', 'meta-llama/llama-3.1-8b-instruct');
	}
}
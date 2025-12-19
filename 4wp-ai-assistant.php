<?php
/**
 * Plugin Name: 4WP AI Assistant
 * Plugin URI: https://github.com/4wpdev/4wp-ai-assistant
 * Description: Universal AI assistant for WordPress. Supports multiple providers (Groq, RunPod, OpenRouter) with RAG capabilities.
 * Version: 1.0.0
 * Author: 4wp.dev
 * Author URI: https://4wp.dev
 * Text Domain: 4wp-ai-assistant
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 8.0
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 * @package ForWP\AIAssistant
 */

namespace ForWP\AIAssistant;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants
define('WP_AI_ASSISTANT_VERSION', '1.0.0');
define('WP_AI_ASSISTANT_PATH', plugin_dir_path(__FILE__));
define('WP_AI_ASSISTANT_URL', plugin_dir_url(__FILE__));
define('WP_AI_ASSISTANT_BASENAME', plugin_basename(__FILE__));
define('WP_AI_ASSISTANT_FILE', __FILE__);

// Load Composer autoloader
if (file_exists(WP_AI_ASSISTANT_PATH . 'vendor/autoload.php')) {
	require_once WP_AI_ASSISTANT_PATH . 'vendor/autoload.php';
}

/**
 * Check if LMS4WP plugin is active (optional integration)
 *
 * @return bool
 */
/**
 * Check if LMS4WP plugin is active (optional integration)
 *
 * @return bool
 */
function wp_ai_assistant_has_lms4wp(): bool
{
	return defined('LMS4WP_PATH') || class_exists('ForWP\LMS\Plugin');
}

/**
 * Main plugin class
 */
class Plugin
{
	/**
	 * Plugin instance
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return self
	 */
	public static function getInstance(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct()
	{
		$this->init();
	}

	/**
	 * Initialize plugin
	 */
	private function init(): void
	{
		// Load .env file early
		$this->loadEnv();

		// Load dependencies
		$this->loadDependencies();

		// Initialize plugin
		add_action('plugins_loaded', [$this, 'loadPlugin'], 10);
	}

	/**
	 * Load .env file
	 */
	private function loadEnv(): void
	{
		$env_file = WP_AI_ASSISTANT_PATH . '.env';
		if (file_exists($env_file)) {
			// Use Dotenv if available
			if (class_exists('\Dotenv\Dotenv')) {
				$dotenv = \Dotenv\Dotenv::createImmutable(WP_AI_ASSISTANT_PATH);
				$dotenv->load();
			} else {
				// Fallback: simple parser
				Core\EnvLoader::load($env_file);
			}
		}
	}

	/**
	 * Load plugin dependencies
	 */
	private function loadDependencies(): void
	{
		$includes_dir = WP_AI_ASSISTANT_PATH . 'includes/';

		// Core (load first)
		require_once $includes_dir . 'Core/EnvLoader.php';
		require_once $includes_dir . 'Core/Extension.php';

		// AI Providers (Abstract first, then implementations)
		require_once $includes_dir . 'AI/ProviderInterface.php';
		require_once $includes_dir . 'AI/AbstractProvider.php';
		require_once $includes_dir . 'AI/Providers/GroqProvider.php';
		require_once $includes_dir . 'AI/Providers/RunPodProvider.php';
		require_once $includes_dir . 'AI/Providers/OpenRouterProvider.php';
		require_once $includes_dir . 'AI/ProviderManager.php';

		// RAG Engine
		require_once $includes_dir . 'RAG/IndexSettings.php';
		require_once $includes_dir . 'RAG/ContentIndexer.php';
		require_once $includes_dir . 'RAG/ContentSearcher.php';
		require_once $includes_dir . 'RAG/RAGEngine.php';

		// Admin
		if (is_admin()) {
			require_once $includes_dir . 'Admin/Menu.php';
			require_once $includes_dir . 'Admin/TestPage.php';
			require_once $includes_dir . 'Admin/RAGTestPage.php';
			require_once $includes_dir . 'Admin/RAGSettingsPage.php';
		}
	}

	/**
	 * Load plugin functionality
	 */
	public function loadPlugin(): void
	{
		// Check requirements
		if (!Core\Extension::checkRequirements()) {
			return;
		}

		// Load text domain
		load_plugin_textdomain(
			'4wp-ai-assistant',
			false,
			dirname(WP_AI_ASSISTANT_BASENAME) . '/languages'
		);

		// Initialize components
		$this->initComponents();
	}

	/**
	 * Initialize plugin components
	 */
	private function initComponents(): void
	{
		// Initialize admin
		if (is_admin()) {
			Admin\Menu::init();
		}

		// Initialize RAG Engine
		RAG\RAGEngine::init();
	}
}

// Initialize plugin
Plugin::getInstance();


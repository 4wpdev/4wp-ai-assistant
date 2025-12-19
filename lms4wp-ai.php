<?php
/**
 * Plugin Name: LMS4WP AI
 * Plugin URI: https://github.com/4wpdev/lms4wp-ai
 * Description: AI integration for LMS4WP using multiple providers (Groq, RunPod, OpenRouter)
 * Version: 1.0.0
 * Author: 4wp.dev
 * Author URI: https://4wp.dev
 * Text Domain: lms4wp-ai
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 8.0
 * Requires Plugins: lms4wp
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 * @package ForWP\LMS\AI
 */

namespace ForWP\LMS\AI;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants
define('LMS4WP_AI_VERSION', '1.0.0');
define('LMS4WP_AI_PATH', plugin_dir_path(__FILE__));
define('LMS4WP_AI_URL', plugin_dir_url(__FILE__));
define('LMS4WP_AI_BASENAME', plugin_basename(__FILE__));
define('LMS4WP_AI_FILE', __FILE__);

/**
 * Check if main LMS4WP plugin is active
 * Use multiple checks to ensure compatibility
 */
function lms4wp_ai_check_dependency(): bool
{
	// Check 1: Check if constant is defined (most reliable)
	if (defined('LMS4WP_PATH')) {
		return true;
	}

	// Check 2: Check if plugin file exists
	$main_plugin_file = WP_PLUGIN_DIR . '/lms4wp/lms4wp.php';
	if (file_exists($main_plugin_file)) {
		// Check if plugin is active
		if (!function_exists('is_plugin_active')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if (is_plugin_active('lms4wp/lms4wp.php')) {
			return true;
		}
	}

	// Check 3: Check if class exists (late check)
	if (class_exists('ForWP\LMS\Plugin')) {
		return true;
	}

	return false;
}

// Early check - if constants not defined, wait for plugins_loaded
if (!defined('LMS4WP_PATH')) {
	// Delay check until plugins_loaded hook
	add_action('plugins_loaded', function () {
		if (!lms4wp_ai_check_dependency()) {
			add_action('admin_notices', function () {
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e('LMS4WP AI', 'lms4wp-ai'); ?></strong>:
						<?php esc_html_e('LMS4WP plugin must be installed and activated.', 'lms4wp-ai'); ?>
					</p>
				</div>
				<?php
			});
		}
	}, 5); // Priority 5 to check early but after main plugin loads
}

// Load Composer autoloader
if (file_exists(LMS4WP_AI_PATH . 'vendor/autoload.php')) {
	require_once LMS4WP_AI_PATH . 'vendor/autoload.php';
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
		// Check dependency first
		if (!lms4wp_ai_check_dependency()) {
			// Will show notice via plugins_loaded hook
			return;
		}

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
		$env_file = LMS4WP_AI_PATH . '.env';
		if (file_exists($env_file)) {
			// Use Dotenv if available
			if (class_exists('\Dotenv\Dotenv')) {
				$dotenv = \Dotenv\Dotenv::createImmutable(LMS4WP_AI_PATH);
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
		$includes_dir = LMS4WP_AI_PATH . 'includes/';

		// Core
		require_once $includes_dir . 'Core/EnvLoader.php';
		require_once $includes_dir . 'Core/Extension.php';

		// AI Providers (Abstract first, then implementations)
		require_once $includes_dir . 'AI/ProviderInterface.php';
		require_once $includes_dir . 'AI/AbstractProvider.php';
		require_once $includes_dir . 'AI/Providers/GroqProvider.php';
		require_once $includes_dir . 'AI/Providers/RunPodProvider.php';
		require_once $includes_dir . 'AI/Providers/OpenRouterProvider.php';
		require_once $includes_dir . 'AI/ProviderManager.php';

		// Admin
		if (is_admin()) {
			require_once $includes_dir . 'Admin/Menu.php';
			require_once $includes_dir . 'Admin/TestPage.php';
		}
	}

	/**
	 * Load plugin functionality
	 */
	public function loadPlugin(): void
	{
		// Double-check dependency
		if (!lms4wp_ai_check_dependency()) {
			return;
		}

		// Check requirements
		if (!Core\Extension::checkRequirements()) {
			return;
		}

		// Load text domain
		load_plugin_textdomain(
			'lms4wp-ai',
			false,
			dirname(LMS4WP_AI_BASENAME) . '/languages'
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
	}
}

// Initialize plugin
Plugin::getInstance();
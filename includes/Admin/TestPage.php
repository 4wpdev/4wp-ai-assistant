<?php
/**
 * AI Test Page
 *
 * Web interface for testing AI agent
 *
 * @package ForWP\AIAssistant\Admin
 */

namespace ForWP\AIAssistant\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\AIAssistant\AI\ProviderManager;
use ForWP\AIAssistant\RAG\RAGEngine;

class TestPage
{
	/**
	 * Render test page
	 */
	public static function render(): void
	{
		// Enqueue styles
		self::enqueueStyles();
		// Handle form submission
		if (isset($_POST['4wp_ai_test']) && check_admin_referer('4wp_ai_test')) {
			$message = sanitize_text_field($_POST['message'] ?? '');
			$provider_id = sanitize_text_field($_POST['provider'] ?? '');
			$result = self::handleTest($message, $provider_id);
		}

		$providers = ProviderManager::getProviders();
		$configured_providers = ProviderManager::getConfiguredProviders();
		$active_provider = ProviderManager::getActiveProvider();
		$active_provider_id = array_search($active_provider, $providers);
		?>
		<div class="wrap wp-ai-assistant-wrap">
			<div class="wp-ai-assistant-header">
				<h1 class="wp-ai-assistant-title">
					<span class="dashicons dashicons-robot"></span>
					<?php esc_html_e('AI Assistant', '4wp-ai-assistant'); ?>
				</h1>
				<p class="wp-ai-assistant-description"><?php esc_html_e('Test and interact with AI providers (Groq, RunPod, OpenRouter)', '4wp-ai-assistant'); ?></p>
			</div>

			<div class="wp-ai-assistant-status">
				<?php if (empty($configured_providers)): ?>
					<div class="wp-ai-assistant-alert wp-ai-assistant-alert-error">
						<span class="dashicons dashicons-warning"></span>
						<div>
							<strong><?php esc_html_e('No Providers Configured', '4wp-ai-assistant'); ?></strong>
							<p><?php esc_html_e('Please configure at least one AI provider in your .env file.', '4wp-ai-assistant'); ?></p>
						</div>
					</div>
				<?php else: ?>
					<div class="wp-ai-assistant-alert wp-ai-assistant-alert-success">
						<span class="dashicons dashicons-yes-alt"></span>
						<div>
							<strong><?php esc_html_e('Active Provider', '4wp-ai-assistant'); ?>: <?php echo esc_html($active_provider->getName()); ?></strong>
							<?php if (!$active_provider->isConfigured()): ?>
								<p style="color: #d63638; margin: 5px 0 0 0;"><?php esc_html_e('Not configured', '4wp-ai-assistant'); ?></p>
							<?php else: ?>
								<p><?php esc_html_e('Ready to use', '4wp-ai-assistant'); ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<div class="wp-ai-assistant-test-container">
				<form method="post" action="" class="wp-ai-assistant-form">
					<?php wp_nonce_field('4wp_ai_test'); ?>
					
					<div class="wp-ai-assistant-form-group">
						<label for="provider" class="wp-ai-assistant-label">
							<span class="dashicons dashicons-admin-plugins"></span>
							<?php esc_html_e('AI Provider', '4wp-ai-assistant'); ?>
						</label>
						<select id="provider" name="provider" class="wp-ai-assistant-select">
							<?php foreach ($providers as $id => $provider): ?>
								<option value="<?php echo esc_attr($id); ?>" 
									<?php selected($id, $active_provider_id); ?>
									<?php echo !$provider->isConfigured() ? 'disabled' : ''; ?>
								>
									<?php echo esc_html($provider->getName()); ?>
									<?php if (!$provider->isConfigured()): ?>
										(<?php esc_html_e('Not configured', '4wp-ai-assistant'); ?>)
									<?php endif; ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="wp-ai-assistant-form-group">
						<label for="message" class="wp-ai-assistant-label">
							<span class="dashicons dashicons-edit"></span>
							<?php esc_html_e('Your Message', '4wp-ai-assistant'); ?>
						</label>
						<textarea 
							id="message" 
							name="message" 
							rows="6" 
							class="wp-ai-assistant-textarea" 
							required
							placeholder="<?php esc_attr_e('Type your message to AI assistant...', '4wp-ai-assistant'); ?>"
						><?php echo isset($_POST['message']) ? esc_textarea($_POST['message']) : ''; ?></textarea>
					</div>

					<div class="wp-ai-assistant-form-actions">
						<button 
							type="submit" 
							name="4wp_ai_test" 
							class="wp-ai-assistant-button wp-ai-assistant-button-primary"
							<?php echo empty($configured_providers) ? 'disabled' : ''; ?>
						>
							<span class="dashicons dashicons-paperclip"></span>
							<?php esc_html_e('Send to AI', '4wp-ai-assistant'); ?>
						</button>
					</div>
				</form>

				<?php if (isset($result)): ?>
					<div class="wp-ai-assistant-result">
						<h2 class="wp-ai-assistant-result-title">
							<span class="dashicons dashicons-format-chat"></span>
							<?php esc_html_e('AI Response', '4wp-ai-assistant'); ?>
						</h2>
						
						<?php if (is_wp_error($result)): ?>
							<div class="wp-ai-assistant-alert wp-ai-assistant-alert-error">
								<span class="dashicons dashicons-dismiss"></span>
								<div>
									<strong><?php esc_html_e('Error', '4wp-ai-assistant'); ?></strong>
									<p><?php echo esc_html($result->get_error_message()); ?></p>
								</div>
							</div>
						<?php elseif (isset($result['success']) && $result['success']): ?>
							<div class="wp-ai-assistant-response">
								<div class="wp-ai-assistant-response-content">
									<?php echo wp_kses_post(nl2br(esc_html($result['message']))); ?>
								</div>
								
								<?php if (isset($result['usage'])): ?>
									<details class="wp-ai-assistant-usage">
										<summary>
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e('Usage Details', '4wp-ai-assistant'); ?>
										</summary>
										<pre><?php echo esc_html(print_r($result['usage'], true)); ?></pre>
									</details>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle test request
	 *
	 * @param string $message User message
	 * @param string $provider_id Provider ID
	 * @return array|\WP_Error
	 */
	private static function handleTest(string $message, string $provider_id = ''): array|\WP_Error
	{
		if (empty($message)) {
			return new \WP_Error('empty_message', __('Message cannot be empty.', '4wp-ai-assistant'));
		}

		// Get provider
		if (!empty($provider_id)) {
			$provider = ProviderManager::getProvider($provider_id);
			if (!$provider || !$provider->isConfigured()) {
				return new \WP_Error('provider_not_configured', __('Selected provider is not configured.', '4wp-ai-assistant'));
			}
		} else {
			$provider = ProviderManager::getActiveProvider();
		}

		// Enhance message with RAG context
		$enhanced_message = RAGEngine::enhanceMessage($message);

		return $provider->sendMessage($enhanced_message);
	}

	/**
	 * Enqueue styles for the admin page
	 */
	private static function enqueueStyles(): void
	{
		?>
		<style>
		.wp-ai-assistant-wrap {
			max-width: 900px;
			margin: 20px 0;
		}

		.wp-ai-assistant-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #fff;
			padding: 30px;
			border-radius: 8px;
			margin-bottom: 30px;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
		}

		.wp-ai-assistant-title {
			margin: 0 0 10px 0;
			font-size: 28px;
			font-weight: 600;
			display: flex;
			align-items: center;
			gap: 10px;
		}

		.wp-ai-assistant-title .dashicons {
			font-size: 32px;
			width: 32px;
			height: 32px;
		}

		.wp-ai-assistant-description {
			margin: 0;
			opacity: 0.9;
			font-size: 14px;
		}

		.wp-ai-assistant-status {
			margin-bottom: 30px;
		}

		.wp-ai-assistant-alert {
			display: flex;
			align-items: flex-start;
			gap: 15px;
			padding: 15px 20px;
			border-radius: 6px;
			margin-bottom: 20px;
		}

		.wp-ai-assistant-alert .dashicons {
			font-size: 24px;
			width: 24px;
			height: 24px;
			margin-top: 2px;
		}

		.wp-ai-assistant-alert-success {
			background: #d1e7dd;
			border-left: 4px solid #00a32a;
			color: #0f5132;
		}

		.wp-ai-assistant-alert-error {
			background: #f8d7da;
			border-left: 4px solid #d63638;
			color: #842029;
		}

		.wp-ai-assistant-alert strong {
			display: block;
			margin-bottom: 5px;
		}

		.wp-ai-assistant-alert p {
			margin: 0;
			font-size: 13px;
		}

		.wp-ai-assistant-test-container {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
			padding: 30px;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
		}

		.wp-ai-assistant-form-group {
			margin-bottom: 25px;
		}

		.wp-ai-assistant-label {
			display: flex;
			align-items: center;
			gap: 8px;
			font-weight: 600;
			margin-bottom: 10px;
			color: #1d2327;
		}

		.wp-ai-assistant-label .dashicons {
			font-size: 18px;
			width: 18px;
			height: 18px;
			color: #2271b1;
		}

		.wp-ai-assistant-select,
		.wp-ai-assistant-textarea {
			width: 100%;
			padding: 12px;
			border: 1px solid #8c8f94;
			border-radius: 4px;
			font-size: 14px;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			transition: border-color 0.2s;
		}

		.wp-ai-assistant-select:focus,
		.wp-ai-assistant-textarea:focus {
			outline: none;
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
		}

		.wp-ai-assistant-textarea {
			resize: vertical;
			min-height: 120px;
		}

		.wp-ai-assistant-form-actions {
			margin-top: 25px;
		}

		.wp-ai-assistant-button {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 12px 24px;
			border: none;
			border-radius: 6px;
			font-size: 14px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s;
			text-decoration: none;
		}

		.wp-ai-assistant-button .dashicons {
			font-size: 18px;
			width: 18px;
			height: 18px;
		}

		.wp-ai-assistant-button-primary {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #fff;
		}

		.wp-ai-assistant-button-primary:hover:not(:disabled) {
			transform: translateY(-1px);
			box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
		}

		.wp-ai-assistant-button-primary:disabled {
			opacity: 0.5;
			cursor: not-allowed;
		}

		.wp-ai-assistant-result {
			margin-top: 30px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
			padding: 30px;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
		}

		.wp-ai-assistant-result-title {
			display: flex;
			align-items: center;
			gap: 10px;
			margin: 0 0 20px 0;
			font-size: 20px;
			color: #1d2327;
		}

		.wp-ai-assistant-result-title .dashicons {
			color: #2271b1;
		}

		.wp-ai-assistant-response {
			background: #f6f7f7;
			border-radius: 6px;
			padding: 20px;
		}

		.wp-ai-assistant-response-content {
			line-height: 1.6;
			color: #1d2327;
			white-space: pre-wrap;
			word-wrap: break-word;
		}

		.wp-ai-assistant-usage {
			margin-top: 20px;
			border-top: 1px solid #c3c4c7;
			padding-top: 15px;
		}

		.wp-ai-assistant-usage summary {
			display: flex;
			align-items: center;
			gap: 8px;
			cursor: pointer;
			color: #2271b1;
			font-weight: 600;
			user-select: none;
		}

		.wp-ai-assistant-usage summary:hover {
			color: #135e96;
		}

		.wp-ai-assistant-usage .dashicons {
			font-size: 16px;
			width: 16px;
			height: 16px;
		}

		.wp-ai-assistant-usage pre {
			background: #1d2327;
			color: #f0f0f1;
			padding: 15px;
			border-radius: 4px;
			overflow-x: auto;
			margin-top: 10px;
			font-size: 12px;
			line-height: 1.5;
		}
		</style>
		<?php
	}
}
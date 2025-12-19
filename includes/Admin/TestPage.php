<?php
/**
 * AI Test Page
 *
 * Web interface for testing AI agent
 *
 * @package ForWP\LMS\AI\Admin
 */

namespace ForWP\LMS\AI\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\LMS\AI\AI\ProviderManager;

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
		if (isset($_POST['lms4wp_ai_test']) && check_admin_referer('lms4wp_ai_test')) {
			$message = sanitize_text_field($_POST['message'] ?? '');
			$provider_id = sanitize_text_field($_POST['provider'] ?? '');
			$result = self::handleTest($message, $provider_id);
		}

		$providers = ProviderManager::getProviders();
		$configured_providers = ProviderManager::getConfiguredProviders();
		$active_provider = ProviderManager::getActiveProvider();
		$active_provider_id = array_search($active_provider, $providers);
		?>
		<div class="wrap lms4wp-ai-wrap">
			<div class="lms4wp-ai-header">
				<h1 class="lms4wp-ai-title">
					<span class="dashicons dashicons-robot"></span>
					<?php esc_html_e('AI Assistant', 'lms4wp-ai'); ?>
				</h1>
				<p class="lms4wp-ai-description"><?php esc_html_e('Test and interact with AI providers (Groq, RunPod, OpenRouter)', 'lms4wp-ai'); ?></p>
			</div>

			<div class="lms4wp-ai-status">
				<?php if (empty($configured_providers)): ?>
					<div class="lms4wp-ai-alert lms4wp-ai-alert-error">
						<span class="dashicons dashicons-warning"></span>
						<div>
							<strong><?php esc_html_e('No Providers Configured', 'lms4wp-ai'); ?></strong>
							<p><?php esc_html_e('Please configure at least one AI provider in your .env file.', 'lms4wp-ai'); ?></p>
						</div>
					</div>
				<?php else: ?>
					<div class="lms4wp-ai-alert lms4wp-ai-alert-success">
						<span class="dashicons dashicons-yes-alt"></span>
						<div>
							<strong><?php esc_html_e('Active Provider', 'lms4wp-ai'); ?>: <?php echo esc_html($active_provider->getName()); ?></strong>
							<?php if (!$active_provider->isConfigured()): ?>
								<p style="color: #d63638; margin: 5px 0 0 0;"><?php esc_html_e('Not configured', 'lms4wp-ai'); ?></p>
							<?php else: ?>
								<p><?php esc_html_e('Ready to use', 'lms4wp-ai'); ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<div class="lms4wp-ai-test-container">
				<form method="post" action="" class="lms4wp-ai-form">
					<?php wp_nonce_field('lms4wp_ai_test'); ?>
					
					<div class="lms4wp-ai-form-group">
						<label for="provider" class="lms4wp-ai-label">
							<span class="dashicons dashicons-admin-plugins"></span>
							<?php esc_html_e('AI Provider', 'lms4wp-ai'); ?>
						</label>
						<select id="provider" name="provider" class="lms4wp-ai-select">
							<?php foreach ($providers as $id => $provider): ?>
								<option value="<?php echo esc_attr($id); ?>" 
									<?php selected($id, $active_provider_id); ?>
									<?php echo !$provider->isConfigured() ? 'disabled' : ''; ?>
								>
									<?php echo esc_html($provider->getName()); ?>
									<?php if (!$provider->isConfigured()): ?>
										(<?php esc_html_e('Not configured', 'lms4wp-ai'); ?>)
									<?php endif; ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="lms4wp-ai-form-group">
						<label for="message" class="lms4wp-ai-label">
							<span class="dashicons dashicons-edit"></span>
							<?php esc_html_e('Your Message', 'lms4wp-ai'); ?>
						</label>
						<textarea 
							id="message" 
							name="message" 
							rows="6" 
							class="lms4wp-ai-textarea" 
							required
							placeholder="<?php esc_attr_e('Type your message to AI assistant...', 'lms4wp-ai'); ?>"
						><?php echo isset($_POST['message']) ? esc_textarea($_POST['message']) : ''; ?></textarea>
					</div>

					<div class="lms4wp-ai-form-actions">
						<button 
							type="submit" 
							name="lms4wp_ai_test" 
							class="lms4wp-ai-button lms4wp-ai-button-primary"
							<?php echo empty($configured_providers) ? 'disabled' : ''; ?>
						>
							<span class="dashicons dashicons-paperclip"></span>
							<?php esc_html_e('Send to AI', 'lms4wp-ai'); ?>
						</button>
					</div>
				</form>

				<?php if (isset($result)): ?>
					<div class="lms4wp-ai-result">
						<h2 class="lms4wp-ai-result-title">
							<span class="dashicons dashicons-format-chat"></span>
							<?php esc_html_e('AI Response', 'lms4wp-ai'); ?>
						</h2>
						
						<?php if (is_wp_error($result)): ?>
							<div class="lms4wp-ai-alert lms4wp-ai-alert-error">
								<span class="dashicons dashicons-dismiss"></span>
								<div>
									<strong><?php esc_html_e('Error', 'lms4wp-ai'); ?></strong>
									<p><?php echo esc_html($result->get_error_message()); ?></p>
								</div>
							</div>
						<?php elseif (isset($result['success']) && $result['success']): ?>
							<div class="lms4wp-ai-response">
								<div class="lms4wp-ai-response-content">
									<?php echo wp_kses_post(nl2br(esc_html($result['message']))); ?>
								</div>
								
								<?php if (isset($result['usage'])): ?>
									<details class="lms4wp-ai-usage">
										<summary>
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e('Usage Details', 'lms4wp-ai'); ?>
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
			return new \WP_Error('empty_message', __('Message cannot be empty.', 'lms4wp-ai'));
		}

		// Get provider
		if (!empty($provider_id)) {
			$provider = ProviderManager::getProvider($provider_id);
			if (!$provider || !$provider->isConfigured()) {
				return new \WP_Error('provider_not_configured', __('Selected provider is not configured.', 'lms4wp-ai'));
			}
		} else {
			$provider = ProviderManager::getActiveProvider();
		}

		return $provider->sendMessage($message);
	}

	/**
	 * Enqueue styles for the admin page
	 */
	private static function enqueueStyles(): void
	{
		?>
		<style>
		.lms4wp-ai-wrap {
			max-width: 900px;
			margin: 20px 0;
		}

		.lms4wp-ai-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #fff;
			padding: 30px;
			border-radius: 8px;
			margin-bottom: 30px;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
		}

		.lms4wp-ai-title {
			margin: 0 0 10px 0;
			font-size: 28px;
			font-weight: 600;
			display: flex;
			align-items: center;
			gap: 10px;
		}

		.lms4wp-ai-title .dashicons {
			font-size: 32px;
			width: 32px;
			height: 32px;
		}

		.lms4wp-ai-description {
			margin: 0;
			opacity: 0.9;
			font-size: 14px;
		}

		.lms4wp-ai-status {
			margin-bottom: 30px;
		}

		.lms4wp-ai-alert {
			display: flex;
			align-items: flex-start;
			gap: 15px;
			padding: 15px 20px;
			border-radius: 6px;
			margin-bottom: 20px;
		}

		.lms4wp-ai-alert .dashicons {
			font-size: 24px;
			width: 24px;
			height: 24px;
			margin-top: 2px;
		}

		.lms4wp-ai-alert-success {
			background: #d1e7dd;
			border-left: 4px solid #00a32a;
			color: #0f5132;
		}

		.lms4wp-ai-alert-error {
			background: #f8d7da;
			border-left: 4px solid #d63638;
			color: #842029;
		}

		.lms4wp-ai-alert strong {
			display: block;
			margin-bottom: 5px;
		}

		.lms4wp-ai-alert p {
			margin: 0;
			font-size: 13px;
		}

		.lms4wp-ai-test-container {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
			padding: 30px;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
		}

		.lms4wp-ai-form-group {
			margin-bottom: 25px;
		}

		.lms4wp-ai-label {
			display: flex;
			align-items: center;
			gap: 8px;
			font-weight: 600;
			margin-bottom: 10px;
			color: #1d2327;
		}

		.lms4wp-ai-label .dashicons {
			font-size: 18px;
			width: 18px;
			height: 18px;
			color: #2271b1;
		}

		.lms4wp-ai-select,
		.lms4wp-ai-textarea {
			width: 100%;
			padding: 12px;
			border: 1px solid #8c8f94;
			border-radius: 4px;
			font-size: 14px;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			transition: border-color 0.2s;
		}

		.lms4wp-ai-select:focus,
		.lms4wp-ai-textarea:focus {
			outline: none;
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
		}

		.lms4wp-ai-textarea {
			resize: vertical;
			min-height: 120px;
		}

		.lms4wp-ai-form-actions {
			margin-top: 25px;
		}

		.lms4wp-ai-button {
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

		.lms4wp-ai-button .dashicons {
			font-size: 18px;
			width: 18px;
			height: 18px;
		}

		.lms4wp-ai-button-primary {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #fff;
		}

		.lms4wp-ai-button-primary:hover:not(:disabled) {
			transform: translateY(-1px);
			box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
		}

		.lms4wp-ai-button-primary:disabled {
			opacity: 0.5;
			cursor: not-allowed;
		}

		.lms4wp-ai-result {
			margin-top: 30px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
			padding: 30px;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
		}

		.lms4wp-ai-result-title {
			display: flex;
			align-items: center;
			gap: 10px;
			margin: 0 0 20px 0;
			font-size: 20px;
			color: #1d2327;
		}

		.lms4wp-ai-result-title .dashicons {
			color: #2271b1;
		}

		.lms4wp-ai-response {
			background: #f6f7f7;
			border-radius: 6px;
			padding: 20px;
		}

		.lms4wp-ai-response-content {
			line-height: 1.6;
			color: #1d2327;
			white-space: pre-wrap;
			word-wrap: break-word;
		}

		.lms4wp-ai-usage {
			margin-top: 20px;
			border-top: 1px solid #c3c4c7;
			padding-top: 15px;
		}

		.lms4wp-ai-usage summary {
			display: flex;
			align-items: center;
			gap: 8px;
			cursor: pointer;
			color: #2271b1;
			font-weight: 600;
			user-select: none;
		}

		.lms4wp-ai-usage summary:hover {
			color: #135e96;
		}

		.lms4wp-ai-usage .dashicons {
			font-size: 16px;
			width: 16px;
			height: 16px;
		}

		.lms4wp-ai-usage pre {
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
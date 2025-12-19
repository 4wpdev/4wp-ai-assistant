<?php
/**
 * RAG Test Page
 *
 * Test and debug RAG Engine
 *
 * @package ForWP\AI\Admin
 */

namespace ForWP\AI\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

use ForWP\AI\RAG\ContentIndexer;
use ForWP\AI\RAG\ContentSearcher;
use ForWP\AI\RAG\RAGEngine;

class RAGTestPage
{
	/**
	 * Render RAG test page
	 */
	public static function render(): void
	{
		// Handle form submission
		if (isset($_POST['rag_test']) && check_admin_referer('rag_test')) {
			$query = sanitize_text_field($_POST['query'] ?? '');
			$results = !empty($query) ? ContentSearcher::search($query, 5) : [];
			$context = !empty($query) ? RAGEngine::getContext($query) : '';
			$enhanced_message = !empty($query) ? RAGEngine::enhanceMessage($query) : '';
		}

		// Get indexed content
		$indexed_content = ContentIndexer::indexContent();
		$total_items = count($indexed_content);

		self::enqueueStyles();
		?>
		<div class="wrap wp-ai-assistant-wrap">
			<div class="wp-ai-assistant-header">
				<h1 class="wp-ai-assistant-title">
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e('RAG Engine Test', '4wp-ai-assistant'); ?>
				</h1>
				<p class="wp-ai-assistant-description">
					<?php esc_html_e('Test content indexing and search functionality', '4wp-ai-assistant'); ?>
				</p>
			</div>

			<!-- Indexed Content Stats -->
			<div class="wp-ai-assistant-stats">
				<div class="wp-ai-assistant-stat-card">
					<div class="stat-number"><?php echo esc_html($total_items); ?></div>
					<div class="stat-label"><?php esc_html_e('Indexed Items', '4wp-ai-assistant'); ?></div>
				</div>
				<div class="wp-ai-assistant-stat-card">
					<div class="stat-number"><?php echo esc_html(count(array_unique(array_column($indexed_content, 'type')))); ?></div>
					<div class="stat-label"><?php esc_html_e('Content Types', '4wp-ai-assistant'); ?></div>
				</div>
			</div>

			<!-- Search Form -->
			<div class="wp-ai-assistant-test-container">
				<h2><?php esc_html_e('Test Search', '4wp-ai-assistant'); ?></h2>
				<form method="post" action="" class="wp-ai-assistant-form">
					<?php wp_nonce_field('rag_test'); ?>
					
					<div class="wp-ai-assistant-form-group">
						<label for="query" class="wp-ai-assistant-label">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e('Search Query', '4wp-ai-assistant'); ?>
						</label>
						<input 
							type="text" 
							id="query" 
							name="query" 
							class="wp-ai-assistant-input" 
							value="<?php echo isset($_POST['query']) ? esc_attr($_POST['query']) : ''; ?>"
							placeholder="<?php esc_attr_e('Enter search query...', '4wp-ai-assistant'); ?>"
							required
						>
					</div>

					<div class="wp-ai-assistant-form-actions">
						<button type="submit" name="rag_test" class="wp-ai-assistant-button wp-ai-assistant-button-primary">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e('Search', '4wp-ai-assistant'); ?>
						</button>
					</div>
				</form>

				<?php if (isset($results) && !empty($results)): ?>
					<!-- Search Results -->
					<div class="wp-ai-assistant-results-section">
						<h3><?php esc_html_e('Search Results', '4wp-ai-assistant'); ?></h3>
						<div class="wp-ai-assistant-results-list">
							<?php foreach ($results as $index => $result): ?>
								<div class="wp-ai-assistant-result-item">
									<div class="result-header">
										<span class="result-number">#<?php echo esc_html($index + 1); ?></span>
										<span class="result-score">Score: <?php echo esc_html($result['relevance_score'] ?? 0); ?></span>
										<span class="result-type"><?php echo esc_html($result['type']); ?></span>
									</div>
									<h4 class="result-title">
										<a href="<?php echo esc_url($result['url']); ?>" target="_blank">
											<?php echo esc_html($result['title']); ?>
										</a>
									</h4>
									<div class="result-content">
										<?php echo esc_html(wp_trim_words($result['content'], 50)); ?>
									</div>
									<div class="result-url">
										<small><?php echo esc_url($result['url']); ?></small>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Context Preview -->
					<?php if (!empty($context)): ?>
						<div class="wp-ai-assistant-context-section">
							<h3><?php esc_html_e('Generated Context (sent to LLM)', '4wp-ai-assistant'); ?></h3>
							<div class="wp-ai-assistant-context-box">
								<pre><?php echo esc_html($context); ?></pre>
							</div>
						</div>
					<?php endif; ?>

					<!-- Enhanced Message Preview -->
					<?php if (!empty($enhanced_message)): ?>
						<div class="wp-ai-assistant-enhanced-section">
							<h3><?php esc_html_e('Enhanced Message (with RAG context)', '4wp-ai-assistant'); ?></h3>
							<div class="wp-ai-assistant-enhanced-box">
								<pre><?php echo esc_html($enhanced_message); ?></pre>
							</div>
						</div>
					<?php endif; ?>
				<?php elseif (isset($results) && empty($results) && !empty($_POST['query'])): ?>
					<div class="wp-ai-assistant-alert wp-ai-assistant-alert-error">
						<span class="dashicons dashicons-info"></span>
						<div>
							<strong><?php esc_html_e('No Results Found', '4wp-ai-assistant'); ?></strong>
							<p><?php esc_html_e('Try different keywords or check if content is indexed.', '4wp-ai-assistant'); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Indexed Content List -->
			<div class="wp-ai-assistant-indexed-section">
				<h2><?php esc_html_e('Indexed Content', '4wp-ai-assistant'); ?></h2>
				<div class="wp-ai-assistant-indexed-list">
					<?php foreach (array_slice($indexed_content, 0, 20) as $item): ?>
						<div class="wp-ai-assistant-indexed-item">
							<div class="indexed-header">
								<span class="indexed-type"><?php echo esc_html($item['type']); ?></span>
								<span class="indexed-id">ID: <?php echo esc_html($item['id']); ?></span>
							</div>
							<h4 class="indexed-title">
								<a href="<?php echo esc_url($item['url']); ?>" target="_blank">
									<?php echo esc_html($item['title']); ?>
								</a>
							</h4>
							<div class="indexed-preview">
								<?php echo esc_html(wp_trim_words($item['content'], 30)); ?>
							</div>
						</div>
					<?php endforeach; ?>
					<?php if ($total_items > 20): ?>
						<p class="indexed-more">
							<?php printf(
								esc_html__('Showing first 20 of %d indexed items', '4wp-ai-assistant'),
								esc_html($total_items)
							); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue styles
	 */
	private static function enqueueStyles(): void
	{
		?>
		<style>
		.wp-ai-assistant-stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 20px;
			margin-bottom: 30px;
		}

		.wp-ai-assistant-stat-card {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
			padding: 20px;
			text-align: center;
		}

		.stat-number {
			font-size: 36px;
			font-weight: 700;
			color: #2271b1;
			margin-bottom: 5px;
		}

		.stat-label {
			font-size: 14px;
			color: #646970;
		}

		.wp-ai-assistant-input {
			width: 100%;
			padding: 12px;
			border: 1px solid #8c8f94;
			border-radius: 4px;
			font-size: 14px;
		}

		.wp-ai-assistant-results-section,
		.wp-ai-assistant-context-section,
		.wp-ai-assistant-enhanced-section,
		.wp-ai-assistant-indexed-section {
			margin-top: 30px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
			padding: 20px;
		}

		.wp-ai-assistant-results-section h3,
		.wp-ai-assistant-context-section h3,
		.wp-ai-assistant-enhanced-section h3,
		.wp-ai-assistant-indexed-section h2 {
			margin-top: 0;
			color: #1d2327;
		}

		.wp-ai-assistant-results-list {
			display: flex;
			flex-direction: column;
			gap: 15px;
		}

		.wp-ai-assistant-result-item {
			background: #f6f7f7;
			border-left: 4px solid #2271b1;
			padding: 15px;
			border-radius: 4px;
		}

		.result-header {
			display: flex;
			gap: 10px;
			align-items: center;
			margin-bottom: 10px;
			font-size: 12px;
		}

		.result-number {
			background: #2271b1;
			color: #fff;
			padding: 2px 8px;
			border-radius: 3px;
			font-weight: 600;
		}

		.result-score {
			background: #00a32a;
			color: #fff;
			padding: 2px 8px;
			border-radius: 3px;
		}

		.result-type {
			background: #f0f0f1;
			padding: 2px 8px;
			border-radius: 3px;
			text-transform: uppercase;
			font-size: 11px;
		}

		.result-title {
			margin: 10px 0;
		}

		.result-title a {
			color: #2271b1;
			text-decoration: none;
		}

		.result-title a:hover {
			text-decoration: underline;
		}

		.result-content {
			color: #646970;
			line-height: 1.6;
			margin-bottom: 10px;
		}

		.result-url {
			font-size: 12px;
			color: #8c8f94;
		}

		.wp-ai-assistant-context-box,
		.wp-ai-assistant-enhanced-box {
			background: #1d2327;
			color: #f0f0f1;
			padding: 15px;
			border-radius: 4px;
			overflow-x: auto;
		}

		.wp-ai-assistant-context-box pre,
		.wp-ai-assistant-enhanced-box pre {
			margin: 0;
			font-family: 'Courier New', monospace;
			font-size: 13px;
			line-height: 1.5;
			white-space: pre-wrap;
			word-wrap: break-word;
		}

		.wp-ai-assistant-indexed-list {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
			gap: 15px;
		}

		.wp-ai-assistant-indexed-item {
			background: #f6f7f7;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 15px;
		}

		.indexed-header {
			display: flex;
			justify-content: space-between;
			margin-bottom: 10px;
			font-size: 12px;
		}

		.indexed-type {
			background: #2271b1;
			color: #fff;
			padding: 2px 8px;
			border-radius: 3px;
			text-transform: uppercase;
			font-size: 11px;
		}

		.indexed-id {
			color: #646970;
		}

		.indexed-title {
			margin: 10px 0;
			font-size: 16px;
		}

		.indexed-title a {
			color: #1d2327;
			text-decoration: none;
		}

		.indexed-title a:hover {
			color: #2271b1;
		}

		.indexed-preview {
			color: #646970;
			font-size: 13px;
			line-height: 1.5;
		}

		.indexed-more {
			grid-column: 1 / -1;
			text-align: center;
			color: #646970;
			font-style: italic;
			margin-top: 10px;
		}
		</style>
		<?php
	}
}


<?php
/**
 * Roadmap Page
 *
 * Static overview of the 5-group plan and current status of each, kept
 * inside wp-admin for visibility (mirrors the full plan document).
 *
 * @package ForWP\AIAssistant\Admin
 */

namespace ForWP\AIAssistant\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class RoadmapPage
{
	/**
	 * @return array<int, array{title:string, status:string, note:string}>
	 */
	protected static function groups(): array
	{
		return [
			[
				'title' => __('1. Extraction', '4wp-ai-assistant'),
				'status' => 'done',
				'note' => __('LLM extracts skills / requirements / recommendations / methodology from each job vacancy post. Stored in dedicated tables, not postmeta.', '4wp-ai-assistant'),
			],
			[
				'title' => __('2. Normalization', '4wp-ai-assistant'),
				'status' => 'done',
				'note' => __('Persisted skill dictionary (forwp_skills + forwp_skill_aliases) folds spelling variants into one canonical term per real-world skill.', '4wp-ai-assistant'),
			],
			[
				'title' => __('3. Statistics', '4wp-ai-assistant'),
				'status' => 'done',
				'note' => __('Skill Frequency page: market-demand ranking per term, per field, via SQL GROUP BY on the dictionary.', '4wp-ai-assistant'),
			],
			[
				'title' => __('4. SEO Strategy & Training Materials', '4wp-ai-assistant'),
				'status' => 'in-progress',
				'note' => __('Content Gap report (SEO Strategy page): high-demand terms with no matching content yet on 4wp.dev become the article/course backlog. Next: cross-reference with 4wp-seo-helper focus keywords and Search Console data instead of manual status only.', '4wp-ai-assistant'),
			],
			[
				'title' => __('5. Resume Generator', '4wp-ai-assistant'),
				'status' => 'paused',
				'note' => __('Two-sided, same matching engine: (a) candidate side — match resume skills against the dictionary and demand stats to score fit and suggest additions; (b) employer side — technical pre-screening as a semi-automated service, matching a candidate against one specific job posting\'s extracted requirements. Paused until group 4 is settled.', '4wp-ai-assistant'),
			],
		];
	}

	/**
	 * Render admin page
	 */
	public static function render(): void
	{
		$labels = [
			'done' => __('Done', '4wp-ai-assistant'),
			'in-progress' => __('In progress', '4wp-ai-assistant'),
			'paused' => __('Paused', '4wp-ai-assistant'),
			'planned' => __('Planned', '4wp-ai-assistant'),
		];
		?>
		<div class="wrap wp-ai-assistant-wrap" style="max-width:900px;">
			<h1>
				<span class="dashicons dashicons-map"></span>
				<?php esc_html_e('Roadmap', '4wp-ai-assistant'); ?>
			</h1>
			<p style="color:#646970;">
				<?php esc_html_e('Job-vacancy data pipeline: extract structured signal from postings, turn it into market-demand statistics, then use that to drive SEO content strategy and, later, a resume tool.', '4wp-ai-assistant'); ?>
			</p>

			<?php foreach (self::groups() as $group): ?>
				<div class="roadmap-item roadmap-<?php echo esc_attr($group['status']); ?>">
					<div class="roadmap-item-head">
						<strong><?php echo esc_html($group['title']); ?></strong>
						<span class="roadmap-badge"><?php echo esc_html($labels[$group['status']] ?? $group['status']); ?></span>
					</div>
					<p><?php echo esc_html($group['note']); ?></p>
				</div>
			<?php endforeach; ?>
		</div>

		<style>
		.roadmap-item {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-left: 4px solid #c3c4c7;
			border-radius: 4px;
			padding: 14px 16px;
			margin: 14px 0;
		}
		.roadmap-item-head { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
		.roadmap-badge {
			font-size: 11px;
			text-transform: uppercase;
			padding: 2px 8px;
			border-radius: 10px;
			background: #f0f0f1;
			color: #50575e;
		}
		.roadmap-done { border-left-color: #46b450; }
		.roadmap-done .roadmap-badge { background: #f0f6e8; color: #2a6a1f; }
		.roadmap-in-progress { border-left-color: #dba617; }
		.roadmap-in-progress .roadmap-badge { background: #fcf9e8; color: #8a6d1e; }
		.roadmap-paused { border-left-color: #8c8f94; }
		.roadmap-planned { border-left-color: #72aee6; }
		</style>
		<?php
	}
}

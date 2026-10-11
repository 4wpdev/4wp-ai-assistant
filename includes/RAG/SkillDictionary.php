<?php
/**
 * Skill Dictionary
 *
 * The actual, persisted normalization layer (group 2): two tables —
 * forwp_skills (one row per real-world skill/term) and
 * forwp_skill_aliases (every raw spelling that maps to one). Replaces the
 * earlier approach of re-normalizing every raw string in PHP on every page
 * load from a hardcoded array; that array's starter pairs are now just the
 * seed data for these tables, and unrecognized raw values automatically
 * become their own new dictionary entry rather than being lost.
 *
 * @package ForWP\AIAssistant\RAG
 */

namespace ForWP\AIAssistant\RAG;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class SkillDictionary
{
	const SEED_OPTION = 'forwp_skill_dictionary_seeded';

	/**
	 * Starter alias => canonical display name pairs, inserted once via
	 * maybeSeed(). After seeding, the dictionary grows only through
	 * resolve() (new terms) and, eventually, manual merges — not by
	 * editing this list and redeploying.
	 *
	 * @return array<string,string>
	 */
	protected static function seedAliases(): array
	{
		return [
			'js' => 'JavaScript',
			'javascript' => 'JavaScript',
			'vanilla js' => 'JavaScript',
			'html' => 'HTML',
			'html5' => 'HTML',
			'css' => 'CSS',
			'css3' => 'CSS',
			'html/css' => 'HTML/CSS',
			'scss' => 'SASS/SCSS',
			'sass' => 'SASS/SCSS',
			'php' => 'PHP',
			'mysql' => 'MySQL',
			'sql' => 'SQL',
			'wordpress' => 'WordPress',
			'wp' => 'WordPress',
			'acf' => 'ACF',
			'acf pro' => 'ACF',
			'advanced custom fields' => 'ACF',
			'advanced custom fields (pro version)' => 'ACF',
			'elementor' => 'Elementor',
			'elementor pro' => 'Elementor',
			'gutenberg' => 'Gutenberg',
			'git' => 'Git',
			'github' => 'Git',
			'gitlab' => 'Git',
			'bitbucket' => 'Git',
			'git/bitbucket' => 'Git',
			'woocommerce' => 'WooCommerce',
			'wpml' => 'WPML',
			'polylang' => 'Polylang',
			'contact form 7' => 'Contact Form 7',
			'cf7' => 'Contact Form 7',
			'wp forms' => 'WPForms',
			'wpforms' => 'WPForms',
			'ci/cd' => 'CI/CD',
			'cicd' => 'CI/CD',
			'github actions' => 'CI/CD',
			'gitlab ci' => 'CI/CD',
			'oop' => 'OOP',
			'object-oriented programming' => 'OOP',
			'object oriented programming' => 'OOP',
			'react' => 'React',
			'reactjs' => 'React',
			'react.js' => 'React',
			'node.js' => 'Node.js',
			'nodejs' => 'Node.js',
			'jquery' => 'jQuery',
			'code review' => 'Code Review',
			'core web vitals' => 'Core Web Vitals',
			'pagespeed insights' => 'PageSpeed/Lighthouse',
			'lighthouse' => 'PageSpeed/Lighthouse',
			'pagespeed / lighthouse' => 'PageSpeed/Lighthouse',
		];
	}

	/**
	 * Insert the starter aliases as real rows — once. Safe to call on
	 * every request; no-op after the first successful run.
	 */
	public static function maybeSeed(): void
	{
		if (get_option(self::SEED_OPTION)) {
			return;
		}

		foreach (self::seedAliases() as $alias => $canonical) {
			$skill_id = self::findOrCreateSkill($canonical);
			self::addAlias($skill_id, $alias);
		}

		update_option(self::SEED_OPTION, 1);
	}

	/**
	 * @param string $raw
	 * @return string Lowercased, trimmed, trailing-punctuation-stripped key
	 */
	protected static function normalizeKey(string $raw): string
	{
		$key = mb_strtolower(trim($raw));
		return rtrim($key, '.,;:');
	}

	/**
	 * Resolve a raw extracted value to a dictionary skill id, creating a
	 * new dictionary entry (using the raw value's own casing) if no known
	 * alias matches yet.
	 *
	 * @param string $raw
	 * @return int
	 */
	public static function resolve(string $raw): int
	{
		global $wpdb;

		$key = self::normalizeKey($raw);
		if ($key === '') {
			$key = mb_strtolower(trim($raw));
		}

		$aliases_table = JobSkillsInstaller::skillAliasesTable();

		$skill_id = $wpdb->get_var($wpdb->prepare(
			"SELECT skill_id FROM {$aliases_table} WHERE alias = %s",
			$key
		));

		if ($skill_id) {
			return (int) $skill_id;
		}

		$skill_id = self::findOrCreateSkill(trim($raw));
		self::addAlias((int) $skill_id, $key);

		return (int) $skill_id;
	}

	/**
	 * @param string $canonical_name
	 * @return int
	 */
	protected static function findOrCreateSkill(string $canonical_name): int
	{
		global $wpdb;
		$dictionary_table = JobSkillsInstaller::skillDictionaryTable();

		$existing = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$dictionary_table} WHERE canonical_name = %s",
			$canonical_name
		));

		if ($existing) {
			return (int) $existing;
		}

		$wpdb->insert($dictionary_table, [
			'canonical_name' => $canonical_name,
			'created_at' => current_time('mysql'),
		], ['%s', '%s']);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $skill_id
	 * @param string $alias Already-normalized key
	 */
	protected static function addAlias(int $skill_id, string $alias): void
	{
		global $wpdb;
		$aliases_table = JobSkillsInstaller::skillAliasesTable();

		// alias is UNIQUE — ignore if another skill already claimed it
		// (first writer wins; a manual merge tool can reassign it later).
		$wpdb->query($wpdb->prepare(
			"INSERT IGNORE INTO {$aliases_table} (skill_id, alias) VALUES (%d, %s)",
			$skill_id,
			$alias
		));
	}

	/**
	 * Populate canonical_skill_id for any job_skills rows that don't have
	 * one yet — e.g. extracted before this dictionary existed, or before a
	 * given install() call added the column. Cheap once caught up.
	 */
	public static function backfill(): void
	{
		global $wpdb;
		$skills_table = JobSkillsInstaller::skillsTable();

		$pending = $wpdb->get_results(
			"SELECT id, value FROM {$skills_table} WHERE canonical_skill_id IS NULL"
		);

		foreach ($pending as $row) {
			$skill_id = self::resolve($row->value);
			$wpdb->update(
				$skills_table,
				['canonical_skill_id' => $skill_id],
				['id' => $row->id],
				['%d'],
				['%d']
			);
		}
	}
}

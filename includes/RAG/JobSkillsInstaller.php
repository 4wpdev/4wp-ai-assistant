<?php
/**
 * Job Skills DB Installer
 *
 * Creates and versions the two tables the extraction pipeline writes to,
 * instead of storing per-job extraction JSON as postmeta. At the scale this
 * is meant to run at (1000+ vacancies, re-run over time), postmeta would
 * mean thousands of large one-off text blobs that are never queried
 * directly — only ever decoded row by row to aggregate. Real tables let
 * stats (group 3) be a plain SQL GROUP BY instead of a full PHP scan.
 *
 * @package ForWP\AIAssistant\RAG
 */

namespace ForWP\AIAssistant\RAG;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class JobSkillsInstaller
{
	/**
	 * Bump this whenever the schema below changes; maybeInstall() re-runs
	 * dbDelta only when the stored version differs.
	 */
	const DB_VERSION = '1.3.0';

	const VERSION_OPTION = 'forwp_job_skills_db_version';

	/**
	 * Create/upgrade tables only if the schema version changed.
	 * Safe to call on every request — cheap when already up to date.
	 */
	public static function maybeInstall(): void
	{
		if (get_option(self::VERSION_OPTION) === self::DB_VERSION) {
			return;
		}

		self::migrateLegacyTableNames();
		self::install();

		// Seed the starter synonym list into real rows (once), then backfill
		// canonical_skill_id for any job_skills rows extracted before the
		// dictionary existed. Both are cheap no-ops once caught up.
		SkillDictionary::maybeSeed();
		SkillDictionary::backfill();

		update_option(self::VERSION_OPTION, self::DB_VERSION);
	}

	/**
	 * One-time rename: the tables were first created as wp_4wp_job_* before
	 * the plugin's actual naming convention (forwp_, as already used by
	 * e.g. the _forwp_seo_* postmeta) was pointed out. Renames in place so
	 * any extraction data already collected under the old names is kept.
	 */
	protected static function migrateLegacyTableNames(): void
	{
		global $wpdb;

		$legacy_extractions = $wpdb->prefix . '4wp_job_extractions';
		$legacy_skills = $wpdb->prefix . '4wp_job_skills';

		$legacy_extractions_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_extractions));
		$legacy_skills_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_skills));

		if ($legacy_extractions_exists && !$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::extractionsTable()))) {
			$wpdb->query("RENAME TABLE {$legacy_extractions} TO " . self::extractionsTable());
		}

		if ($legacy_skills_exists && !$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::skillsTable()))) {
			$wpdb->query("RENAME TABLE {$legacy_skills} TO " . self::skillsTable());
		}
	}

	/**
	 * Create/upgrade the tables via dbDelta (idempotent by design).
	 */
	public static function install(): void
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$extractions_table = self::extractionsTable();
		$skills_table = self::skillsTable();
		$dictionary_table = self::skillDictionaryTable();
		$aliases_table = self::skillAliasesTable();

		// One row per job post: whether/when it was successfully processed.
		// Absence of a 'done' row here is what makes a job "pending" — a
		// failed attempt intentionally leaves no row, so it's retried.
		$sql_extractions = "CREATE TABLE {$extractions_table} (
			job_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'done',
			model VARCHAR(100) NULL,
			extracted_at DATETIME NOT NULL,
			PRIMARY KEY  (job_id)
		) {$charset_collate};";

		// One row per extracted item (a single skill, requirement, etc.).
		// canonical_skill_id links it to the normalized dictionary entry
		// below — that's what makes frequency counting a plain GROUP BY
		// instead of re-normalizing every raw string on every page load.
		$sql_skills = "CREATE TABLE {$skills_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id BIGINT UNSIGNED NOT NULL,
			field_type VARCHAR(20) NOT NULL,
			value VARCHAR(255) NOT NULL,
			canonical_skill_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY job_id (job_id),
			KEY field_type (field_type),
			KEY value (value),
			KEY canonical_skill_id (canonical_skill_id)
		) {$charset_collate};";

		// The normalized dictionary: one row per real-world skill/term,
		// under whatever display name we first saw for it.
		// content_status / content_url: manual SEO Strategy tracking (group
		// 4) — whether 4wp.dev already has content targeting this term, so
		// the Content Gap report doesn't need to re-derive it every load.
		$sql_dictionary = "CREATE TABLE {$dictionary_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			canonical_name VARCHAR(255) NOT NULL,
			category VARCHAR(50) NULL,
			content_status VARCHAR(20) NOT NULL DEFAULT 'missing',
			content_url VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY canonical_name (canonical_name)
		) {$charset_collate};";

		// Every spelling variant that resolves to a dictionary entry
		// ('js', 'javascript', ... -> the JavaScript row). Looking up an
		// incoming raw value is a single indexed lookup on `alias`.
		$sql_aliases = "CREATE TABLE {$aliases_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			skill_id BIGINT UNSIGNED NOT NULL,
			alias VARCHAR(255) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY alias (alias),
			KEY skill_id (skill_id)
		) {$charset_collate};";

		dbDelta($sql_extractions);
		dbDelta($sql_dictionary);
		dbDelta($sql_aliases);
		dbDelta($sql_skills);
	}

	/**
	 * @return string Full table name for per-job extraction status
	 */
	public static function extractionsTable(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'forwp_job_extractions';
	}

	/**
	 * @return string Full table name for extracted skill/requirement rows
	 */
	public static function skillsTable(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'forwp_job_skills';
	}

	/**
	 * @return string Full table name for the normalized skill dictionary
	 */
	public static function skillDictionaryTable(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'forwp_skills';
	}

	/**
	 * @return string Full table name for skill spelling-variant aliases
	 */
	public static function skillAliasesTable(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'forwp_skill_aliases';
	}
}

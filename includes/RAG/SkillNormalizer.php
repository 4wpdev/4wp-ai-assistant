<?php
/**
 * Skill Normalizer
 *
 * Folds raw extracted values (skills/requirements/recommendations/
 * methodology strings) to a canonical display term, so frequency counts
 * aren't split across spelling variants ("JS" vs "JavaScript" vs
 * "Javascript"). Deliberately simple and data-driven: the alias list below
 * starts small and grows as real variants show up in extractions — per the
 * plan, this was intentionally NOT designed upfront, only after seeing
 * actual patterns in the extracted data.
 *
 * @package ForWP\AIAssistant\RAG
 */

namespace ForWP\AIAssistant\RAG;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class SkillNormalizer
{
	/**
	 * Lowercase alias => canonical display name.
	 *
	 * Seeded from the WordPress-developer vacancy sample; extend this list
	 * as new raw variants turn up on the Skill Frequency page.
	 *
	 * @return array<string,string>
	 */
	protected static function aliases(): array
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
	 * Fold a raw extracted value to a canonical display term. Falls back
	 * to the trimmed original when no alias is known for it.
	 *
	 * @param string $raw
	 * @return string
	 */
	public static function canonicalize(string $raw): string
	{
		$key = mb_strtolower(trim($raw));
		$key = rtrim($key, '.,;:');

		$aliases = self::aliases();

		return $aliases[$key] ?? trim($raw);
	}
}

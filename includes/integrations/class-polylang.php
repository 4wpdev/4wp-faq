<?php
/**
 * Polylang integration for FAQ registry and categories.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ\Integrations;

use ForWP\FAQ\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Language-aware helpers when Polylang is active.
 */
final class Polylang {

	/**
	 * Register hooks when Polylang is available.
	 */
	public static function init(): void {
		if ( ! self::is_active() ) {
			return;
		}

		add_filter( 'pll_get_post_types', [ __CLASS__, 'register_post_type' ], 10, 2 );
		add_filter( 'pll_get_taxonomies', [ __CLASS__, 'register_taxonomy' ], 10, 2 );
	}

	/**
	 * Whether Polylang APIs are loaded.
	 */
	public static function is_active(): bool {
		return function_exists( 'pll_get_post_language' ) && function_exists( 'pll_set_term_language' );
	}

	/**
	 * Expose registry CPT to Polylang settings.
	 *
	 * @param array<string, string> $post_types  Post types.
	 * @param bool                  $is_settings Settings screen context.
	 * @return array<string, string>
	 */
	public static function register_post_type( $post_types, $is_settings ) {
		if ( ! $is_settings || ! Settings::is_setup_complete() ) {
			return $post_types;
		}

		$slug                      = Settings::get_post_type();
		$post_types[ $slug ]       = $slug;

		return $post_types;
	}

	/**
	 * Expose FAQ category taxonomy to Polylang settings.
	 *
	 * @param array<string, string> $taxonomies  Taxonomies.
	 * @param bool                    $is_settings Settings screen context.
	 * @return array<string, string>
	 */
	public static function register_taxonomy( $taxonomies, $is_settings ) {
		if ( ! $is_settings || ! Settings::is_setup_complete() ) {
			return $taxonomies;
		}

		$slug                        = Settings::get_taxonomy();
		$taxonomies[ $slug ]         = $slug;

		return $taxonomies;
	}

	/**
	 * Language slug for a post, or empty when unknown / monolingual.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_post_language( $post_id ): string {
		if ( ! self::is_active() || $post_id <= 0 ) {
			return '';
		}

		$lang = pll_get_post_language( $post_id );

		return is_string( $lang ) ? $lang : '';
	}

	/**
	 * Assign a language to a registry post when Polylang is active.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lang    Language slug.
	 */
	public static function set_post_language( $post_id, $lang ): void {
		if ( ! self::is_active() || $post_id <= 0 || '' === $lang || ! function_exists( 'pll_set_post_language' ) ) {
			return;
		}

		pll_set_post_language( $post_id, $lang );
	}

	/**
	 * Resolve a term ID to the translation for a language.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $lang    Language slug.
	 */
	public static function resolve_term_for_language( $term_id, $lang ): int {
		$term_id = (int) $term_id;
		if ( $term_id <= 0 ) {
			return 0;
		}

		if ( ! self::is_active() || '' === $lang || ! function_exists( 'pll_get_term' ) ) {
			return $term_id;
		}

		$translated = pll_get_term( $term_id, $lang );

		return is_numeric( $translated ) && (int) $translated > 0 ? (int) $translated : $term_id;
	}

	/**
	 * Find or create a taxonomy term in a specific language.
	 *
	 * @param string $name     Term name.
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $lang     Language slug.
	 */
	public static function ensure_term_by_name( $name, $taxonomy, $lang = '' ): int {
		$name = trim( (string) $name );
		if ( '' === $name || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}

		if ( self::is_active() && '' !== $lang ) {
			$existing_terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'name'       => $name,
					'lang'       => $lang,
					'hide_empty' => false,
					'number'     => 1,
				]
			);

			if ( ! is_wp_error( $existing_terms ) && ! empty( $existing_terms[0] ) && $existing_terms[0] instanceof \WP_Term ) {
				return (int) $existing_terms[0]->term_id;
			}

			$created = wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $created ) || empty( $created['term_id'] ) ) {
				return 0;
			}

			$term_id = (int) $created['term_id'];
			pll_set_term_language( $term_id, $lang );

			/** @see do_action('forwp_faq_category_term_created') */
			do_action( 'forwp_faq_category_term_created', $term_id, $taxonomy, $lang );

			return $term_id;
		}

		$term = term_exists( $name, $taxonomy );
		if ( is_array( $term ) && ! empty( $term['term_id'] ) ) {
			$term_id = (int) $term['term_id'];
			self::maybe_set_term_language( $term_id, $lang );
			return $term_id;
		}

		$created = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $created ) || empty( $created['term_id'] ) ) {
			return 0;
		}

		$term_id = (int) $created['term_id'];
		self::maybe_set_term_language( $term_id, $lang );

		/**
		 * Fires after a FAQ category term is created during scan.
		 *
		 * @param int    $term_id  Term ID.
		 * @param string $taxonomy Taxonomy slug.
		 * @param string $lang     Language slug.
		 */
		do_action( 'forwp_faq_category_term_created', $term_id, $taxonomy, $lang );

		return $term_id;
	}

	/**
	 * @param int    $term_id Term ID.
	 * @param string $lang    Language slug.
	 */
	private static function maybe_set_term_language( $term_id, $lang ): void {
		if ( ! self::is_active() || '' === $lang || $term_id <= 0 ) {
			return;
		}

		pll_set_term_language( $term_id, $lang );
	}
}

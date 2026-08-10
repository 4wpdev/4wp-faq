<?php
/**
 * Resolve block category settings to taxonomy term IDs during scan.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

use ForWP\FAQ\Integrations\Polylang;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps forwp/faq block attributes to faq-category term IDs.
 */
final class Category_Resolver {

	public const MODE_NONE     = 'none';
	public const MODE_EXISTING = 'existing';
	public const MODE_NEW      = 'new';

	/**
	 * Resolve category term IDs from block attributes and source post language.
	 *
	 * @param array<string, mixed> $attrs          Block attributes.
	 * @param int                  $source_post_id Source post ID.
	 * @return int[]
	 */
	public static function resolve_from_block_attrs( $attrs, $source_post_id ) {
		if ( ! Settings::is_setup_complete() || ! is_array( $attrs ) ) {
			return [];
		}

		$taxonomy = Settings::get_taxonomy();
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$mode = isset( $attrs['categoryMode'] ) ? sanitize_key( (string) $attrs['categoryMode'] ) : self::MODE_NONE;
		if ( self::MODE_NONE === $mode || '' === $mode ) {
			return [];
		}

		$lang    = Polylang::get_post_language( $source_post_id );
		$term_id = 0;

		if ( self::MODE_EXISTING === $mode ) {
			$term_id = Polylang::resolve_term_for_language( (int) ( $attrs['categoryTermId'] ?? 0 ), $lang );
		}

		if ( self::MODE_NEW === $mode ) {
			$name    = sanitize_text_field( (string) ( $attrs['categoryName'] ?? '' ) );
			$term_id = Polylang::ensure_term_by_name( $name, $taxonomy, $lang );
		}

		if ( $term_id <= 0 || ! term_exists( $term_id, $taxonomy ) ) {
			return [];
		}

		$term_ids = [ $term_id ];

		/**
		 * Filter term IDs resolved from a forwp/faq block before registry sync.
		 *
		 * @param int[]                $term_ids       Term IDs.
		 * @param array<string, mixed> $attrs          Block attributes.
		 * @param int                  $source_post_id Source post ID.
		 * @param string               $lang           Source post language slug.
		 */
		$term_ids = apply_filters( 'forwp_faq_block_category_term_ids', $term_ids, $attrs, $source_post_id, $lang );

		return array_values( array_unique( array_filter( array_map( 'intval', (array) $term_ids ) ) ) );
	}

	/**
	 * List FAQ category terms for the block editor.
	 *
	 * @param int    $post_id Source post ID (for language filter).
	 * @param string $lang    Optional language override.
	 * @return array<int, array{id:int,name:string,slug:string}>
	 */
	public static function list_editor_terms( $post_id = 0, $lang = '' ) {
		if ( ! Settings::is_setup_complete() ) {
			return [];
		}

		$taxonomy = Settings::get_taxonomy();
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$post_id = (int) $post_id;
		$lang    = is_string( $lang ) ? sanitize_key( $lang ) : '';

		if ( '' === $lang && $post_id > 0 ) {
			$lang = Polylang::get_post_language( $post_id );
		}

		$args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		];

		if ( '' !== $lang && Polylang::is_active() ) {
			$args['lang'] = $lang;
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$items = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$items[] = [
				'id'   => (int) $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			];
		}

		return $items;
	}
}

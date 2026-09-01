<?php
/**
 * FAQ category query var and one-level SEO permalinks on the host page/post.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pretty / query-string category URLs for FAQ lists.
 */
class Faq_Filter {
	public const QUERY_VAR = 'faq_cat';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'query_vars', [ __CLASS__, 'register_query_var' ] );
		add_action( 'parse_request', [ __CLASS__, 'parse_request' ] );
		add_filter( 'redirect_canonical', [ __CLASS__, 'filter_redirect_canonical' ], 10, 2 );
	}

	/**
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public static function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Map /current-page/{category-slug}/ back to the host page plus faq_cat.
	 *
	 * @param \WP $wp WP request.
	 */
	public static function parse_request( $wp ) {
		if ( ! $wp instanceof \WP || is_admin() ) {
			return;
		}

		$request = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
		if ( '' === $request || false === strpos( $request, '/' ) ) {
			return;
		}

		$parts = explode( '/', $request );
		if ( count( $parts ) < 2 ) {
			return;
		}

		$cat_slug = sanitize_title( (string) end( $parts ) );
		if ( '' === $cat_slug || ! self::is_category_slug( $cat_slug ) ) {
			return;
		}

		$parent_path = implode( '/', array_slice( $parts, 0, -1 ) );
		if ( '' === $parent_path ) {
			return;
		}

		if ( self::path_is_real_content( $request ) ) {
			return;
		}

		$host_id = self::get_id_from_path( $parent_path );
		if ( $host_id <= 0 ) {
			return;
		}

		$host = get_post( $host_id );
		if ( ! $host instanceof \WP_Post || 'publish' !== $host->post_status ) {
			return;
		}

		$wp->query_vars = self::query_vars_for_post( $host );
		$wp->query_vars[ self::QUERY_VAR ] = $cat_slug;
		unset( $wp->query_vars['error'] );
	}

	/**
	 * Keep the extra category segment from being stripped.
	 *
	 * @param string|false $redirect_url  Canonical redirect.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public static function filter_redirect_canonical( $redirect_url, $requested_url ) {
		unset( $requested_url );

		if ( self::get_active_slug() ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Active category slug from the request.
	 *
	 * @return string
	 */
	public static function get_active_slug() {
		$slug = get_query_var( self::QUERY_VAR, '' );
		if ( ! is_string( $slug ) || '' === $slug ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET[ self::QUERY_VAR ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$slug = wp_unslash( (string) $_GET[ self::QUERY_VAR ] );
			}
		}

		$slug = sanitize_title( $slug );
		if ( '' === $slug || ! self::is_category_slug( $slug ) ) {
			return '';
		}

		return $slug;
	}

	/**
	 * URL for a category on the current host page/post.
	 *
	 * @param string $slug    Term slug (empty = all).
	 * @param bool   $seo_urls Pretty one-level permalinks.
	 * @return string
	 */
	public static function get_category_url( $slug, $seo_urls ) {
		$base = self::get_host_permalink();
		$slug = sanitize_title( (string) $slug );

		if ( ! $seo_urls || '' === $slug ) {
			return $base;
		}

		return trailingslashit( $base ) . rawurlencode( $slug ) . '/';
	}

	/**
	 * Permalink of the page/post that hosts the FAQ blocks.
	 *
	 * @return string
	 */
	public static function get_host_permalink() {
		$permalink = get_permalink();
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return $permalink;
		}

		return home_url( '/' );
	}

	/**
	 * @param string $slug Term slug.
	 * @return bool
	 */
	public static function is_category_slug( $slug ) {
		$slug     = sanitize_title( (string) $slug );
		$taxonomy = Settings::get_taxonomy();

		if ( '' === $slug || ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		$term = get_term_by( 'slug', $slug, $taxonomy );

		return $term instanceof \WP_Term;
	}

	/**
	 * @param string $path URL path without leading/trailing slash.
	 * @return bool
	 */
	private static function path_is_real_content( $path ) {
		return self::get_id_from_path( $path ) > 0;
	}

	/**
	 * @param string $path URL path without leading/trailing slash.
	 * @return int
	 */
	private static function get_id_from_path( $path ) {
		$path = trim( (string) $path, '/' );
		if ( '' === $path ) {
			return 0;
		}

		$url = home_url( user_trailingslashit( $path ) );
		$id  = url_to_postid( $url );

		if ( $id > 0 ) {
			return (int) $id;
		}

		$page = get_page_by_path( $path, OBJECT, [ 'page', 'post' ] );

		return $page instanceof \WP_Post ? (int) $page->ID : 0;
	}

	/**
	 * @param \WP_Post $post Host post.
	 * @return array<string, mixed>
	 */
	private static function query_vars_for_post( $post ) {
		if ( 'page' === $post->post_type ) {
			return [
				'page_id' => (int) $post->ID,
			];
		}

		return [
			'p'         => (int) $post->ID,
			'post_type' => $post->post_type,
			'name'      => $post->post_name,
		];
	}
}

<?php
namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin options: setup flag, CPT slug, taxonomy slug.
 */
class Settings {
	public const OPTION_SETUP_STATUS   = 'forwp_faq_setup_status';
	public const OPTION_SETUP_COMPLETE = 'forwp_faq_setup_complete';
	public const OPTION_POST_TYPE      = 'forwp_faq_post_type';
	public const OPTION_TAXONOMY       = 'forwp_faq_taxonomy';

	/** When true, FAQPage JSON-LD is output unless a block opts out. */
	public const OPTION_OUTPUT_JSON_LD = 'forwp_faq_output_json_ld';

	public const STATUS_PENDING  = 'pending';
	public const STATUS_COMPLETE = 'complete';
	public const STATUS_SKIPPED  = 'skipped';

	public const DEFAULT_POST_TYPE = 'faq';
	public const DEFAULT_TAXONOMY  = 'faq-category';

	/** @deprecated Use get_post_type() — legacy installs only. */
	public const LEGACY_POST_TYPE = 'forwp_faq';

	/**
	 * Whether the FAQ registry (CPT + scan) is configured.
	 */
	public static function is_setup_complete() {
		return self::STATUS_COMPLETE === self::get_setup_status();
	}

	/**
	 * User chose to skip registry setup for now.
	 */
	public static function is_setup_skipped() {
		return self::STATUS_SKIPPED === self::get_setup_status();
	}

	/**
	 * Setup wizard still needs a decision (not complete, not skipped).
	 */
	public static function is_setup_pending() {
		return self::STATUS_PENDING === self::get_setup_status();
	}

	/**
	 * @return string pending|complete|skipped
	 */
	public static function get_setup_status() {
		$status = get_option( self::OPTION_SETUP_STATUS, '' );

		if ( is_string( $status ) && in_array( $status, [ self::STATUS_PENDING, self::STATUS_COMPLETE, self::STATUS_SKIPPED ], true ) ) {
			return $status;
		}

		if ( (bool) get_option( self::OPTION_SETUP_COMPLETE, false ) ) {
			return self::STATUS_COMPLETE;
		}

		return self::STATUS_PENDING;
	}

	/**
	 * Configured FAQ registry post type slug.
	 */
	public static function get_post_type() {
		$slug = get_option( self::OPTION_POST_TYPE, '' );
		if ( is_string( $slug ) && '' !== $slug ) {
			return $slug;
		}

		return self::DEFAULT_POST_TYPE;
	}

	/**
	 * Configured taxonomy slug for the FAQ CPT.
	 */
	public static function get_taxonomy() {
		$slug = get_option( self::OPTION_TAXONOMY, '' );
		if ( is_string( $slug ) && '' !== $slug ) {
			return $slug;
		}

		return self::DEFAULT_TAXONOMY;
	}

	/**
	 * Persist setup choices and mark wizard complete.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $taxonomy  Taxonomy slug.
	 */
	public static function complete_setup( $post_type, $taxonomy ) {
		update_option( self::OPTION_POST_TYPE, $post_type );
		update_option( self::OPTION_TAXONOMY, $taxonomy );
		update_option( self::OPTION_SETUP_STATUS, self::STATUS_COMPLETE );
		update_option( self::OPTION_SETUP_COMPLETE, true );
	}

	/**
	 * Defer registry setup; block + JSON-LD keep working.
	 */
	public static function skip_setup() {
		update_option( self::OPTION_SETUP_STATUS, self::STATUS_SKIPPED );
		update_option( self::OPTION_SETUP_COMPLETE, false );
	}

	/**
	 * Site-wide default for FAQPage JSON-LD (per-block can override).
	 */
	public static function is_output_json_ld_enabled() {
		return (bool) get_option( self::OPTION_OUTPUT_JSON_LD, false );
	}

	/**
	 * @param bool $enabled Whether JSON-LD is on by default site-wide.
	 */
	public static function set_output_json_ld( $enabled ) {
		update_option( self::OPTION_OUTPUT_JSON_LD, $enabled ? 1 : 0 );
	}

	/**
	 * Return wizard to pending so slugs can be changed (see reset_setup).
	 */
	public static function reset_setup() {
		$taxonomy = self::get_taxonomy();

		if ( taxonomy_exists( $taxonomy ) ) {
			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				]
			);

			if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
				foreach ( $terms as $term_id ) {
					wp_delete_term( (int) $term_id, $taxonomy );
				}
			}
		}

		update_option( self::OPTION_SETUP_STATUS, self::STATUS_PENDING );
		update_option( self::OPTION_SETUP_COMPLETE, false );
		delete_option( 'forwp_faq_last_scan_at' );
	}

	/**
	 * Validate a post type slug for registration.
	 *
	 * @param string $slug Raw slug.
	 * @return true|\WP_Error
	 */
	public static function validate_post_type_slug( $slug ) {
		$slug = sanitize_key( (string) $slug );

		if ( strlen( $slug ) < 2 || strlen( $slug ) > 20 ) {
			return new \WP_Error(
				'forwp_faq_invalid_post_type',
				__( 'Post type slug must be between 2 and 20 characters.', '4wp-faq' )
			);
		}

		if ( in_array( $slug, self::reserved_post_type_slugs(), true ) ) {
			return new \WP_Error(
				'forwp_faq_reserved_post_type',
				__( 'That post type slug is reserved by WordPress.', '4wp-faq' )
			);
		}

		if ( post_type_exists( $slug ) ) {
			if ( self::is_setup_complete() && $slug === self::get_post_type() ) {
				return true;
			}

			if ( self::is_adoptable_post_type( $slug ) ) {
				return true;
			}

			return new \WP_Error(
				'forwp_faq_post_type_exists',
				__( 'That post type is already registered. Enter your existing FAQ registry slug (e.g. forwp_faq) or choose a new unused slug.', '4wp-faq' )
			);
		}

		return true;
	}

	/**
	 * Default CPT slug suggested in the setup wizard.
	 */
	public static function get_suggested_post_type_slug() {
		if ( self::has_legacy_registry_posts() ) {
			return self::LEGACY_POST_TYPE;
		}

		return self::DEFAULT_POST_TYPE;
	}

	/**
	 * Whether the site has FAQ registry posts from a pre-wizard install.
	 */
	public static function has_legacy_registry_posts() {
		$posts = get_posts(
			[
				'post_type'      => self::LEGACY_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		return ! empty( $posts );
	}

	/**
	 * Existing registry CPT the wizard may adopt (not register again).
	 *
	 * @param string $slug Post type slug.
	 */
	public static function is_adoptable_post_type( $slug ) {
		if ( self::LEGACY_POST_TYPE === $slug && self::has_legacy_registry_posts() ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate a taxonomy slug for registration.
	 *
	 * @param string $slug Raw slug.
	 * @return true|\WP_Error
	 */
	public static function validate_taxonomy_slug( $slug ) {
		$slug = sanitize_key( (string) $slug );

		if ( strlen( $slug ) < 2 || strlen( $slug ) > 32 ) {
			return new \WP_Error(
				'forwp_faq_invalid_taxonomy',
				__( 'Taxonomy slug must be between 2 and 32 characters.', '4wp-faq' )
			);
		}

		if ( in_array( $slug, self::reserved_taxonomy_slugs(), true ) ) {
			return new \WP_Error(
				'forwp_faq_reserved_taxonomy',
				__( 'That taxonomy slug is reserved by WordPress.', '4wp-faq' )
			);
		}

		if ( taxonomy_exists( $slug ) ) {
			if ( self::is_setup_complete() && $slug === self::get_taxonomy() ) {
				return true;
			}

			if ( self::is_adoptable_taxonomy( $slug ) ) {
				return true;
			}

			return new \WP_Error(
				'forwp_faq_taxonomy_exists',
				__( 'That taxonomy is already registered. Choose a new slug or reuse an existing FAQ category taxonomy if it belongs to this plugin.', '4wp-faq' )
			);
		}

		return true;
	}

	/**
	 * Existing taxonomy the wizard may adopt.
	 *
	 * @param string $slug Taxonomy slug.
	 */
	public static function is_adoptable_taxonomy( $slug ) {
		if ( self::DEFAULT_TAXONOMY !== $slug ) {
			return false;
		}

		return taxonomy_exists( $slug ) && self::has_legacy_registry_posts();
	}

	/**
	 * Post type slugs WordPress reserves.
	 *
	 * @return string[]
	 */
	private static function reserved_post_type_slugs() {
		return [
			'post',
			'page',
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
			'action',
			'author',
			'order',
			'theme',
			'acf-field',
			'acf-field-group',
		];
	}

	/**
	 * Taxonomy slugs WordPress reserves.
	 *
	 * @return string[]
	 */
	private static function reserved_taxonomy_slugs() {
		return [
			'category',
			'post_tag',
			'nav_menu',
			'link_category',
			'post_format',
			'wp_theme',
			'wp_template_part_area',
			'wp_pattern_category',
		];
	}
}

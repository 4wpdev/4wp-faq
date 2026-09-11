<?php
/**
 * Drag-and-drop order for FAQ categories (wp_terms.term_order).
 *
 * WordPress core maps orderby=term_order to tr.term_order (relationships) and,
 * without object_ids, falls back to term_id — so we use a custom orderby key
 * that resolves to t.term_order for admin lists and front-end trees.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin sortable list table for the FAQ category taxonomy.
 */
class Term_Order {
	/**
	 * Custom get_terms orderby that maps to t.term_order.
	 */
	private const ORDERBY_KEY = 'forwp_faq_term_order';

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! Settings::is_setup_complete() ) {
			return;
		}

		self::ensure_term_order_column();

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin' ] );
		add_action( 'wp_ajax_forwp_faq_term_order', [ __CLASS__, 'ajax_save_order' ] );
		add_action( 'created_term', [ __CLASS__, 'on_created_term' ], 10, 3 );
		add_filter( 'get_terms_args', [ __CLASS__, 'filter_get_terms_args' ], 10, 2 );
		add_filter( 'get_terms_orderby', [ __CLASS__, 'filter_get_terms_orderby' ], 10, 3 );
	}

	/**
	 * Some installs lack wp_terms.term_order (removed / never migrated).
	 * Without it, ORDER BY t.term_order empties the categories list.
	 *
	 * @return bool Whether the column exists after this call.
	 */
	public static function ensure_term_order_column(): bool {
		static $ready = null;
		if ( null !== $ready ) {
			return $ready;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW COLUMNS FROM ' . $wpdb->terms . ' LIKE %s',
				'term_order'
			)
		);

		if ( $exists ) {
			$ready = true;
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query(
			"ALTER TABLE {$wpdb->terms} ADD COLUMN term_order INT(11) NOT NULL DEFAULT 0"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW COLUMNS FROM ' . $wpdb->terms . ' LIKE %s',
				'term_order'
			)
		);

		$ready = (bool) $exists;
		return $ready;
	}

	/**
	 * Whether the query targets the FAQ category taxonomy.
	 *
	 * @param string|string[] $taxonomies Taxonomy slug(s).
	 */
	private static function is_faq_taxonomy( $taxonomies ): bool {
		$faq = Settings::get_taxonomy();
		if ( '' === $faq ) {
			return false;
		}

		if ( is_string( $taxonomies ) ) {
			return $taxonomies === $faq;
		}

		if ( ! is_array( $taxonomies ) || empty( $taxonomies ) ) {
			return false;
		}

		$taxonomies = array_map( 'strval', $taxonomies );
		return 1 === count( $taxonomies ) && $faq === $taxonomies[0];
	}

	/**
	 * Force FAQ terms to sort by t.term_order unless the admin clicked a column.
	 *
	 * @param array           $args       get_terms args.
	 * @param string|string[] $taxonomies Taxonomies.
	 * @return array
	 */
	public static function filter_get_terms_args( $args, $taxonomies ) {
		if ( ! self::is_faq_taxonomy( $taxonomies ) || ! self::ensure_term_order_column() ) {
			return $args;
		}

		$orderby = isset( $args['orderby'] ) ? $args['orderby'] : 'name';
		if ( is_array( $orderby ) ) {
			return $args;
		}

		$orderby = (string) $orderby;

		if ( 'term_order' === $orderby || self::ORDERBY_KEY === $orderby ) {
			$args['orderby'] = self::ORDERBY_KEY;
			if ( empty( $args['order'] ) ) {
				$args['order'] = 'ASC';
			}
			return $args;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table sort state.
		$request_orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['orderby'] ) ) : '';
		if ( '' !== $request_orderby ) {
			return $args;
		}

		// Default FAQ queries (admin hierarchy + front) use saved drag order.
		if ( in_array( $orderby, [ '', 'name' ], true ) ) {
			$args['orderby'] = self::ORDERBY_KEY;
			if ( empty( $args['order'] ) ) {
				$args['order'] = 'ASC';
			}
		}

		return $args;
	}

	/**
	 * Map custom orderby to the terms table column.
	 *
	 * @param string          $orderby    SQL ORDER BY expression.
	 * @param array           $args       Query vars.
	 * @param string|string[] $taxonomies Taxonomies.
	 * @return string
	 */
	public static function filter_get_terms_orderby( $orderby, $args, $taxonomies ) {
		if ( ! self::is_faq_taxonomy( $taxonomies ) || ! self::ensure_term_order_column() ) {
			return $orderby;
		}

		$key = isset( $args['orderby'] ) ? $args['orderby'] : '';
		if ( self::ORDERBY_KEY === $key || 'term_order' === $key ) {
			return 't.term_order, t.name';
		}

		return $orderby;
	}

	/**
	 * Scripts on the taxonomy list table.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public static function enqueue_admin( $hook_suffix ) {
		if ( 'edit-tags.php' !== $hook_suffix ) {
			return;
		}

		$taxonomy = Settings::get_taxonomy();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( (string) $_GET['taxonomy'] ) ) : '';
		if ( $current !== $taxonomy ) {
			return;
		}

		$tax_obj = get_taxonomy( $taxonomy );
		if ( ! $tax_obj || ! current_user_can( $tax_obj->cap->edit_terms ) ) {
			return;
		}

		self::maybe_backfill_orders();

		$parents = [];
		$terms   = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => self::ORDERBY_KEY,
				'order'      => 'ASC',
			]
		);
		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					$parents[ (string) $term->term_id ] = (int) $term->parent;
				}
			}
		}

		wp_enqueue_script( 'jquery-ui-sortable' );
		if ( wp_is_mobile() ) {
			wp_enqueue_script( 'jquery-touch-punch' );
		}

		// Bump cache when assets change independently of plugin version.
		$js_mtime  = (string) filemtime( FORWP_FAQ_PLUGIN_DIR . 'assets/term-order.js' );
		$css_mtime = (string) filemtime( FORWP_FAQ_PLUGIN_DIR . 'assets/term-order.css' );
		$asset_ver = FORWP_FAQ_VERSION . '.' . $js_mtime . '.' . $css_mtime;

		wp_enqueue_script(
			'forwp-faq-term-order',
			FORWP_FAQ_PLUGIN_URL . 'assets/term-order.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			$asset_ver,
			true
		);
		wp_localize_script(
			'forwp-faq-term-order',
			'forwpFaqTermOrder',
			[
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'forwp_faq_term_order' ),
				'taxonomy' => $taxonomy,
				'parents'  => $parents,
				'i18n'     => [
					'saving' => __( 'Saving order…', '4wp-faq' ),
					'saved'  => __( 'Category order saved.', '4wp-faq' ),
					'error'  => __( 'Could not save category order.', '4wp-faq' ),
					'hint'   => __( 'Drag rows to set the order used in admin lists and on the front end.', '4wp-faq' ),
				],
			]
		);
		wp_enqueue_style(
			'forwp-faq-term-order',
			FORWP_FAQ_PLUGIN_URL . 'assets/term-order.css',
			[],
			$asset_ver
		);
	}

	/**
	 * Assign sequential term_order when every FAQ term is still 0.
	 */
	public static function maybe_backfill_orders() {
		$taxonomy = Settings::get_taxonomy();
		if ( ! taxonomy_exists( $taxonomy ) || ! self::ensure_term_order_column() ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$nonzero = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(t.term_id) FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = %s AND t.term_order > 0",
				$taxonomy
			)
		);

		if ( $nonzero > 0 ) {
			return;
		}

		$terms = get_terms(
			[
				'taxonomy'               => $taxonomy,
				'hide_empty'             => false,
				'orderby'                => 'name',
				'order'                  => 'ASC',
				'update_term_meta_cache' => false,
			]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		// Prefer hierarchical name order: parents first, then children.
		$by_parent = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$by_parent[ (int) $term->parent ][] = $term;
		}

		foreach ( $by_parent as &$group ) {
			usort(
				$group,
				static function ( $a, $b ) {
					return strcasecmp( $a->name, $b->name );
				}
			);
		}
		unset( $group );

		$position = 1;
		$walk     = static function ( $parent_id ) use ( &$walk, &$by_parent, &$position, $wpdb, $taxonomy ) {
			if ( empty( $by_parent[ $parent_id ] ) ) {
				return;
			}
			foreach ( $by_parent[ $parent_id ] as $term ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->terms,
					[ 'term_order' => $position ],
					[ 'term_id' => (int) $term->term_id ],
					[ '%d' ],
					[ '%d' ]
				);
				++$position;
				$walk( (int) $term->term_id );
			}
		};
		$walk( 0 );

		clean_term_cache( wp_list_pluck( $terms, 'term_id' ), $taxonomy );
	}

	/**
	 * New terms go to the end of the list.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public static function on_created_term( $term_id, $tt_id, $taxonomy ) {
		unset( $tt_id );

		if ( (string) $taxonomy !== Settings::get_taxonomy() ) {
			return;
		}

		self::assign_default_order( (int) $term_id );
	}

	/**
	 * New terms go to the end of the list.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function assign_default_order( $term_id ) {
		global $wpdb;

		$term_id  = (int) $term_id;
		$taxonomy = Settings::get_taxonomy();
		if ( $term_id <= 0 || ! taxonomy_exists( $taxonomy ) || ! self::ensure_term_order_column() ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$max = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(t.term_order) FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = %s",
				$taxonomy
			)
		);

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->terms,
			[ 'term_order' => $max + 1 ],
			[ 'term_id' => $term_id ],
			[ '%d' ],
			[ '%d' ]
		);
		clean_term_cache( $term_id, $taxonomy );
	}

	/**
	 * Persist drag-and-drop order.
	 */
	public static function ajax_save_order() {
		check_ajax_referer( 'forwp_faq_term_order', 'nonce' );

		if ( ! self::ensure_term_order_column() ) {
			wp_send_json_error( [ 'message' => __( 'Could not prepare category order storage.', '4wp-faq' ) ], 500 );
		}

		$taxonomy = Settings::get_taxonomy();
		$tax_obj  = get_taxonomy( $taxonomy );
		if ( ! $tax_obj || ! current_user_can( $tax_obj->cap->edit_terms ) ) {
			wp_send_json_error( [ 'message' => __( 'You cannot reorder FAQ categories.', '4wp-faq' ) ], 403 );
		}

		$posted = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( (string) $_POST['taxonomy'] ) ) : '';
		if ( $posted !== $taxonomy ) {
			wp_send_json_error( [ 'message' => __( 'Invalid taxonomy.', '4wp-faq' ) ], 400 );
		}

		$ids = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid order payload.', '4wp-faq' ) ], 400 );
		}

		global $wpdb;
		$position = 1;
		$updated  = [];

		foreach ( $ids as $id ) {
			$term_id = (int) $id;
			if ( $term_id <= 0 ) {
				continue;
			}

			$term = get_term( $term_id, $taxonomy );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->terms,
				[ 'term_order' => $position ],
				[ 'term_id' => $term_id ],
				[ '%d' ],
				[ '%d' ]
			);
			$updated[] = $term_id;
			++$position;
		}

		if ( empty( $updated ) ) {
			wp_send_json_error( [ 'message' => __( 'No categories updated.', '4wp-faq' ) ], 400 );
		}

		clean_term_cache( $updated, $taxonomy );
		wp_send_json_success(
			[
				'message' => __( 'Category order saved.', '4wp-faq' ),
				'count'   => count( $updated ),
			]
		);
	}
}

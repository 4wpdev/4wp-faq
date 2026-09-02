<?php
/**
 * Drag-and-drop order for FAQ categories (term_order).
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
	 * Hooks.
	 */
	public static function init() {
		if ( ! Settings::is_setup_complete() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin' ] );
		add_action( 'wp_ajax_forwp_faq_term_order', [ __CLASS__, 'ajax_save_order' ] );
		add_action( 'created_term', [ __CLASS__, 'on_created_term' ], 10, 3 );
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
		$current  = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( (string) $_GET['taxonomy'] ) ) : '';
		if ( $current !== $taxonomy ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'forwp-faq-term-order',
			FORWP_FAQ_PLUGIN_URL . 'assets/term-order.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			FORWP_FAQ_VERSION,
			true
		);
		wp_localize_script(
			'forwp-faq-term-order',
			'forwpFaqTermOrder',
			[
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'forwp_faq_term_order' ),
				'taxonomy' => $taxonomy,
			]
		);
		wp_enqueue_style(
			'forwp-faq-term-order',
			FORWP_FAQ_PLUGIN_URL . 'assets/term-order.css',
			[],
			FORWP_FAQ_VERSION
		);
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
		if ( $term_id <= 0 || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$max = (int) $wpdb->get_var( "SELECT MAX(term_order) FROM {$wpdb->terms}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( [ 'message' => __( 'You cannot reorder FAQ categories.', '4wp-faq' ) ], 403 );
		}

		$taxonomy = Settings::get_taxonomy();
		$posted   = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( (string) $_POST['taxonomy'] ) ) : '';
		if ( $posted !== $taxonomy ) {
			wp_send_json_error( [ 'message' => __( 'Invalid taxonomy.', '4wp-faq' ) ], 400 );
		}

		$ids = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid order payload.', '4wp-faq' ) ], 400 );
		}

		global $wpdb;
		$position = 1;

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
			++$position;
		}

		clean_term_cache( array_map( 'intval', $ids ), $taxonomy );
		wp_send_json_success();
	}
}

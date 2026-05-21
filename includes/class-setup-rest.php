<?php
namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoints for setup wizard (React admin UI).
 *
 * All routes require manage_options via permission_callback.
 */
class Setup_Rest {
	/**
	 * Register routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'forwp-faq/v1',
			'/setup/complete',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'complete_setup' ],
				'permission_callback' => [ __CLASS__, 'can_manage' ],
				'args'                => [
					'post_type' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'taxonomy'  => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		register_rest_route(
			'forwp-faq/v1',
			'/setup/skip',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'skip_setup' ],
				'permission_callback' => [ __CLASS__, 'can_manage' ],
			]
		);
	}

	/**
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function complete_setup( $request ) {
		$post_type = $request->get_param( 'post_type' );
		$taxonomy  = $request->get_param( 'taxonomy' );

		$post_check = Settings::validate_post_type_slug( $post_type );
		if ( is_wp_error( $post_check ) ) {
			return $post_check;
		}

		$tax_check = Settings::validate_taxonomy_slug( $taxonomy );
		if ( is_wp_error( $tax_check ) ) {
			return $tax_check;
		}

		Settings::complete_setup( $post_type, $taxonomy );
		delete_option( 'forwp_faq_show_setup_notice' );

		Plugin::register_post_type();
		Plugin::register_taxonomy();
		Plugin::register_post_meta();
		flush_rewrite_rules();
		Plugin::schedule_scan();

		return new \WP_REST_Response(
			[
				'redirect' => add_query_arg(
					'forwp_faq_setup_done',
					'1',
					admin_url( 'edit.php?post_type=' . rawurlencode( $post_type ) )
				),
			],
			200
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function skip_setup( $request ) {
		unset( $request );

		Settings::skip_setup();
		delete_option( 'forwp_faq_show_setup_notice' );

		return new \WP_REST_Response(
			[
				'redirect' => add_query_arg(
					'forwp_faq_setup_skipped',
					'1',
					admin_url()
				),
			],
			200
		);
	}
}

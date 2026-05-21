<?php
namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API for the FAQ admin settings app.
 *
 * All routes under forwp-faq/v1 require manage_options via permission_callback.
 */
class Admin_Rest {
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
			'/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'get_settings' ],
					'permission_callback' => [ __CLASS__, 'can_manage' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'update_settings' ],
					'permission_callback' => [ __CLASS__, 'can_manage' ],
					'args'                => [
						'output_json_ld' => [
							'type'     => 'boolean',
							'required' => false,
						],
					],
				],
			]
		);

		register_rest_route(
			'forwp-faq/v1',
			'/registry',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'get_registry' ],
				'permission_callback' => [ __CLASS__, 'can_manage' ],
			]
		);

		register_rest_route(
			'forwp-faq/v1',
			'/registry/scan',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'run_scan' ],
				'permission_callback' => [ __CLASS__, 'can_manage' ],
			]
		);

		register_rest_route(
			'forwp-faq/v1',
			'/setup/reset',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'reset_setup' ],
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
	 * @return \WP_REST_Response
	 */
	public static function get_settings() {
		return new \WP_REST_Response(
			[
				'output_json_ld' => Settings::is_output_json_ld_enabled(),
				'setup_complete' => Settings::is_setup_complete(),
				'setup_skipped'  => Settings::is_setup_skipped(),
				'setup_url'      => Setup_Wizard::get_page_url(),
			],
			200
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function update_settings( $request ) {
		if ( $request->has_param( 'output_json_ld' ) ) {
			Settings::set_output_json_ld( (bool) $request->get_param( 'output_json_ld' ) );
		}

		return self::get_settings();
	}

	/**
	 * Registry summary for the settings UI.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_registry() {
		$stats = Plugin::get_registry_stats();

		if ( ! Settings::is_setup_complete() ) {
			return new \WP_REST_Response(
				[
					'setup_complete' => false,
					'setup_url'      => Setup_Wizard::get_page_url(),
					'stats'          => $stats,
				],
				200
			);
		}

		$post_type = Settings::get_post_type();
		$counts    = wp_count_posts( $post_type );
		$total     = 0;

		if ( $counts ) {
			foreach ( (array) $counts as $count ) {
				$total += (int) $count;
			}
		}

		$last_scan = (int) get_option( 'forwp_faq_last_scan_at', 0 );

		return new \WP_REST_Response(
			[
				'setup_complete'  => true,
				'post_type'       => $post_type,
				'taxonomy'        => Settings::get_taxonomy(),
				'registry_count'  => $total,
				'last_scan'       => $last_scan,
				'last_scan_label' => $last_scan ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_scan ) : '',
				'registry_url'    => admin_url( 'edit.php?post_type=' . rawurlencode( $post_type ) ),
				'stats'           => $stats,
			],
			200
		);
	}

	/**
	 * Run FAQ registry scan immediately.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function run_scan() {
		if ( ! Settings::is_setup_complete() ) {
			return new \WP_Error(
				'forwp_faq_setup_required',
				__( 'Complete FAQ setup before running a scan.', '4wp-faq' ),
				[ 'status' => 400 ]
			);
		}

		Plugin::scan_all_posts();
		update_option( 'forwp_faq_last_scan_at', time() );

		return self::get_registry();
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function reset_setup( $request ) {
		unset( $request );

		Settings::reset_setup();
		flush_rewrite_rules();

		return new \WP_REST_Response(
			[
				'redirect' => Setup_Wizard::get_page_url(),
				'message'  => __(
					'Setup has been reset. FAQ categories were removed. Complete setup again to change registry slugs; existing registry posts stay on the previous post type until you scan again.',
					'4wp-faq'
				),
			],
			200
		);
	}
}

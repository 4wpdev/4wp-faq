<?php
/**
 * REST routes for the block editor (FAQ categories panel).
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor-facing REST API.
 */
final class Editor_Rest {

	/**
	 * Register routes.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			'forwp-faq/v1',
			'/editor/categories',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'get_categories' ],
				'permission_callback' => [ __CLASS__, 'can_edit_content' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'lang'    => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);
	}

	/**
	 * @return bool
	 */
	public static function can_edit_content() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_categories( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$lang    = (string) $request->get_param( 'lang' );

		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_REST_Response(
				[
					'setup_complete' => Settings::is_setup_complete(),
					'taxonomy'       => Settings::get_taxonomy(),
					'terms'          => [],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'setup_complete' => Settings::is_setup_complete(),
				'taxonomy'       => Settings::get_taxonomy(),
				'terms'          => Category_Resolver::list_editor_terms( $post_id, $lang ),
			],
			200
		);
	}
}

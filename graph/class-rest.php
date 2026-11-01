<?php
/**
 * REST: GET {source namespace}/graph for every registered, ready source.
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph;

use ForWP\Graph\Contracts\Relation_Source_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rest {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		foreach ( Source_Registry::all() as $source ) {
			$namespace = trim( $source->get_rest_namespace(), '/' );
			if ( '' === $namespace ) {
				continue;
			}

			register_rest_route(
				$namespace,
				'/graph',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static function ( \WP_REST_Request $request ) use ( $source ) {
						return Rest::handle( $source, $request );
					},
					'permission_callback' => static function () use ( $source ) {
						return current_user_can( $source->get_capability() );
					},
					'args'                => self::build_args( $source ),
				)
			);
		}
	}

	/**
	 * @param Relation_Source_Interface $source  Source.
	 * @param \WP_REST_Request          $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle( Relation_Source_Interface $source, \WP_REST_Request $request ) {
		if ( ! $source->is_ready() ) {
			return new \WP_Error( 'forwp_graph_source_not_ready', 'Graph source is not ready.', array( 'status' => 409 ) );
		}

		$args = array();
		foreach ( array_keys( $source->get_args_schema() ) as $name ) {
			$args[ $name ] = $request->get_param( $name );
		}

		$graph = Cache::remember( $source, $args );
		if ( is_wp_error( $graph ) ) {
			return $graph;
		}

		$renderer          = Renderer_Registry::active( $source->get_slug() );
		$graph['renderer'] = $renderer ? $renderer->get_slug() : '';

		return rest_ensure_response( $graph );
	}

	/**
	 * @param Relation_Source_Interface $source Source.
	 * @return array<string, array<string, mixed>>
	 */
	private static function build_args( Relation_Source_Interface $source ): array {
		$out = array();
		foreach ( $source->get_args_schema() as $name => $def ) {
			$type = isset( $def['type'] ) && in_array( $def['type'], array( 'string', 'integer', 'boolean' ), true ) ? $def['type'] : 'string';

			$out[ sanitize_key( $name ) ] = array(
				'type'              => $type,
				'default'           => isset( $def['default'] ) ? $def['default'] : null,
				'sanitize_callback' => 'integer' === $type ? 'absint' : ( 'boolean' === $type ? 'rest_sanitize_boolean' : 'sanitize_text_field' ),
			);
		}
		return $out;
	}
}

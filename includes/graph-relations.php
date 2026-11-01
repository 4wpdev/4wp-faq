<?php
/**
 * Connect FAQ relations to the shared graph layer.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'forwp_graph_layer_loaded',
	static function () {
		require_once __DIR__ . '/class-graph-relations.php';

		add_filter(
			'forwp_graph_sources',
			static function ( $sources ) {
				$sources   = is_array( $sources ) ? $sources : array();
				$sources[] = new Graph_Relations();
				return $sources;
			}
		);

		add_filter(
			'forwp_graph_renderer',
			static function ( $renderer, $source_slug = '' ) {
				return Graph_Relations::SLUG === $source_slug ? Settings::get_graph_renderer() : $renderer;
			},
			10,
			2
		);
	}
);

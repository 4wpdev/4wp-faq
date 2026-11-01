<?php
/**
 * Graph layer loader (shared, embedded per plugin).
 *
 * Every 4WP plugin that ships graph/ registers its copy as a candidate.
 * One boot picks the newest version and loads it exactly once.
 * Boot contract v1 is frozen: later copies may add files, never change selection.
 *
 * @package ForWP\Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Version of THIS copy of the layer. Bump on any change inside graph/ (except domain cases).
$forwp_graph_this_version = '1.1.1';

if ( ! isset( $GLOBALS['forwp_graph_layer_candidates'] ) || ! is_array( $GLOBALS['forwp_graph_layer_candidates'] ) ) {
	$GLOBALS['forwp_graph_layer_candidates'] = array();
}

$GLOBALS['forwp_graph_layer_candidates'][] = array(
	'version' => $forwp_graph_this_version,
	'dir'     => __DIR__ . '/',
	'url'     => plugin_dir_url( __FILE__ ),
);

unset( $forwp_graph_this_version );

if ( ! function_exists( 'forwp_graph_layer_boot' ) ) {
	/**
	 * Load the newest registered copy of the graph layer. Runs once.
	 */
	function forwp_graph_layer_boot() {
		if ( defined( 'FORWP_GRAPH_LAYER_LOADED' ) ) {
			return;
		}

		$candidates = isset( $GLOBALS['forwp_graph_layer_candidates'] ) ? (array) $GLOBALS['forwp_graph_layer_candidates'] : array();
		$best       = null;

		foreach ( $candidates as $candidate ) {
			if ( empty( $candidate['version'] ) || empty( $candidate['dir'] ) ) {
				continue;
			}
			if ( null === $best || version_compare( $candidate['version'], $best['version'], '>' ) ) {
				$best = $candidate;
			}
		}

		if ( null === $best || ! is_readable( $best['dir'] . 'bootstrap.php' ) ) {
			return;
		}

		define( 'FORWP_GRAPH_LAYER_LOADED', true );
		define( 'FORWP_GRAPH_LAYER_VERSION', $best['version'] );
		define( 'FORWP_GRAPH_LAYER_DIR', $best['dir'] );
		define( 'FORWP_GRAPH_LAYER_URL', trailingslashit( $best['url'] ) );

		require_once $best['dir'] . 'bootstrap.php';

		/**
		 * Fires once the newest graph layer is loaded. Domain sources register here.
		 *
		 * @param string $version Loaded layer version.
		 */
		do_action( 'forwp_graph_layer_loaded', FORWP_GRAPH_LAYER_VERSION );
	}

	if ( did_action( 'plugins_loaded' ) ) {
		add_action( 'init', 'forwp_graph_layer_boot', 0 );
	} else {
		add_action( 'plugins_loaded', 'forwp_graph_layer_boot', 1 );
	}
}

// Domain relations (nodes, edges, types) are NOT part of this layer.
// Each plugin defines them in its own code and registers on 'forwp_graph_layer_loaded'.

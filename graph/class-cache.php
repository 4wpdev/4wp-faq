<?php
/**
 * Graph cache. The graph is derived data; the domain stays the source of truth.
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph;

use ForWP\Graph\Contracts\Relation_Source_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cache {

	private const PREFIX = 'forwp_graph_';

	/**
	 * Return cached contract or build, normalize and cache it.
	 *
	 * @param Relation_Source_Interface $source Source.
	 * @param array<string, mixed>      $args   Sanitized args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function remember( Relation_Source_Interface $source, array $args ) {
		ksort( $args );
		$key = self::PREFIX . md5( $source->get_slug() . '|' . $source->get_cache_version() . '|' . FORWP_GRAPH_LAYER_VERSION . '|' . wp_json_encode( $args ) );

		/** @param bool $enabled Disable to always rebuild (debugging). */
		$enabled = (bool) apply_filters( 'forwp_graph_cache_enabled', true, $source->get_slug() );

		if ( $enabled ) {
			$hit = get_transient( $key );
			if ( is_array( $hit ) ) {
				$hit['cached'] = true;
				return $hit;
			}
		}

		$raw = $source->build( $args );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$graph = Contract::normalize( $source->get_slug(), is_array( $raw ) ? $raw : array() );
		if ( is_wp_error( $graph ) ) {
			return $graph;
		}

		/**
		 * Post-process a normalized graph (Analyzer hooks here in Stage 2).
		 *
		 * @param array  $graph Contract.
		 * @param string $slug  Source slug.
		 * @param array  $args  Args.
		 */
		$graph = (array) apply_filters( 'forwp_graph_built', $graph, $source->get_slug(), $args );

		if ( $enabled ) {
			set_transient( $key, $graph, DAY_IN_SECONDS );
		}

		$graph['cached'] = false;
		return $graph;
	}
}

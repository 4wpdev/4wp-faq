<?php
/**
 * Registry of relation sources (one per plugin domain).
 *
 * Plugins register with:
 *   add_filter( 'forwp_graph_sources', fn( $map ) => $map + [ 'faq' => new My_Source() ] );
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph;

use ForWP\Graph\Contracts\Relation_Source_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Source_Registry {

	/**
	 * @var array<string, Relation_Source_Interface>|null
	 */
	private static $sources = null;

	/**
	 * @return array<string, Relation_Source_Interface>
	 */
	public static function all(): array {
		if ( null !== self::$sources ) {
			return self::$sources;
		}

		$filtered = apply_filters( 'forwp_graph_sources', array() );
		$out      = array();

		foreach ( is_array( $filtered ) ? $filtered : array() as $source ) {
			if ( $source instanceof Relation_Source_Interface ) {
				$out[ sanitize_key( $source->get_slug() ) ] = $source;
			}
		}

		self::$sources = $out;
		return self::$sources;
	}

	/**
	 * @param string $slug Slug.
	 * @return Relation_Source_Interface|null
	 */
	public static function get( string $slug ) {
		$all  = self::all();
		$slug = sanitize_key( $slug );
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/**
	 * Reset (tests).
	 */
	public static function reset(): void {
		self::$sources = null;
	}
}

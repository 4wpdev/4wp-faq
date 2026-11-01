<?php
/**
 * Registry of renderers (JS graph libraries). Same pattern as 4WP Weather / Drive.
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph;

use ForWP\Graph\Contracts\Renderer_Interface;
use ForWP\Graph\Renderers\Cytoscape_Renderer;
use ForWP\Graph\Renderers\Placeholder_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Renderer_Registry {

	public const DEFAULT_SLUG = 'cytoscape';

	/**
	 * @var array<string, Renderer_Interface>|null
	 */
	private static $renderers = null;

	/**
	 * @return array<string, Renderer_Interface>
	 */
	public static function all(): array {
		if ( null !== self::$renderers ) {
			return self::$renderers;
		}

		$map = array(
			'cytoscape' => new Cytoscape_Renderer(),
			'sigma'     => new Placeholder_Renderer( 'sigma', 'Sigma.js' ),
			'd3'        => new Placeholder_Renderer( 'd3', 'D3' ),
			'mermaid'   => new Placeholder_Renderer( 'mermaid', 'Mermaid' ),
		);

		$filtered = apply_filters( 'forwp_graph_renderers', $map );
		$out      = array();

		foreach ( is_array( $filtered ) ? $filtered : $map as $renderer ) {
			if ( $renderer instanceof Renderer_Interface ) {
				$out[ sanitize_key( $renderer->get_slug() ) ] = $renderer;
			}
		}

		self::$renderers = $out;
		return self::$renderers;
	}

	/**
	 * @param string $slug Slug.
	 * @return Renderer_Interface|null
	 */
	public static function get( string $slug ) {
		$all  = self::all();
		$slug = sanitize_key( $slug );
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/**
	 * Renderer chosen for one source (each plugin keeps its own setting),
	 * falling back to the first implemented one.
	 *
	 * @param string $source_slug Source slug, e.g. 'faq'.
	 * @return Renderer_Interface|null
	 */
	public static function active( string $source_slug = '' ) {
		/**
		 * @param string $renderer    Renderer slug.
		 * @param string $source_slug Source asking.
		 */
		$slug     = (string) apply_filters( 'forwp_graph_renderer', self::DEFAULT_SLUG, $source_slug );
		$renderer = self::get( $slug );
		if ( $renderer && $renderer->is_implemented() ) {
			return $renderer;
		}
		foreach ( self::all() as $candidate ) {
			if ( $candidate->is_implemented() ) {
				return $candidate;
			}
		}
		return null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function get_choices(): array {
		$out = array();
		foreach ( self::all() as $renderer ) {
			$out[] = array(
				'value'    => $renderer->get_slug(),
				'label'    => $renderer->get_label(),
				'disabled' => ! $renderer->is_implemented(),
			);
		}
		return $out;
	}

	/**
	 * Reset (tests).
	 */
	public static function reset(): void {
		self::$renderers = null;
	}
}

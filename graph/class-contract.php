<?php
/**
 * Neutral graph contract: nodes + edges. Domain-agnostic.
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contract {

	public const SCHEMA_VERSION = 1;

	/**
	 * Hard cap per response; sources should paginate/filter beyond it.
	 */
	public const MAX_NODES = 5000;
	public const MAX_EDGES = 20000;

	/**
	 * Node helper.
	 *
	 * @param string               $id     Namespaced id, e.g. 'faq:12'.
	 * @param string               $type   Source-declared node type.
	 * @param string               $label  Display label.
	 * @param array<string, mixed> $extra  url, weight, meta.
	 * @return array<string, mixed>
	 */
	public static function node( string $id, string $type, string $label, array $extra = array() ): array {
		return array(
			'id'     => $id,
			'type'   => $type,
			'label'  => $label,
			'url'    => isset( $extra['url'] ) ? (string) $extra['url'] : '',
			'weight' => isset( $extra['weight'] ) ? (float) $extra['weight'] : 0.0,
			'meta'   => isset( $extra['meta'] ) && is_array( $extra['meta'] ) ? $extra['meta'] : array(),
		);
	}

	/**
	 * Edge helper.
	 *
	 * @param string $source Node id.
	 * @param string $target Node id.
	 * @param string $type   Source-declared edge type.
	 * @param float  $weight Weight.
	 * @return array<string, mixed>
	 */
	public static function edge( string $source, string $target, string $type, float $weight = 1.0 ): array {
		return array(
			'id'     => $type . ':' . $source . '>' . $target,
			'source' => $source,
			'target' => $target,
			'type'   => $type,
			'weight' => $weight,
		);
	}

	/**
	 * Validate and normalize a source's raw output into the contract.
	 * Drops invalid/duplicate nodes and dangling/duplicate edges.
	 *
	 * @param string               $source_slug Source slug.
	 * @param array<string, mixed> $raw         Raw build() output.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function normalize( string $source_slug, array $raw ) {
		$raw_nodes = isset( $raw['nodes'] ) && is_array( $raw['nodes'] ) ? $raw['nodes'] : array();
		$raw_edges = isset( $raw['edges'] ) && is_array( $raw['edges'] ) ? $raw['edges'] : array();

		if ( count( $raw_nodes ) > self::MAX_NODES || count( $raw_edges ) > self::MAX_EDGES ) {
			return new \WP_Error( 'forwp_graph_too_large', 'Graph exceeds the per-response limit. Narrow the request.', array( 'status' => 413 ) );
		}

		$nodes = array();
		foreach ( $raw_nodes as $node ) {
			if ( ! is_array( $node ) || empty( $node['id'] ) || empty( $node['type'] ) ) {
				continue;
			}
			$id = (string) $node['id'];
			if ( isset( $nodes[ $id ] ) ) {
				continue;
			}
			$nodes[ $id ] = self::node(
				$id,
				sanitize_key( (string) $node['type'] ),
				self::plain_text( isset( $node['label'] ) ? (string) $node['label'] : $id ),
				array(
					'url'    => isset( $node['url'] ) ? esc_url_raw( (string) $node['url'] ) : '',
					'weight' => isset( $node['weight'] ) ? $node['weight'] : 0,
					'meta'   => isset( $node['meta'] ) ? $node['meta'] : array(),
				)
			);
		}

		$edges = array();
		foreach ( $raw_edges as $edge ) {
			if ( ! is_array( $edge ) || empty( $edge['source'] ) || empty( $edge['target'] ) || empty( $edge['type'] ) ) {
				continue;
			}
			$from = (string) $edge['source'];
			$to   = (string) $edge['target'];
			if ( ! isset( $nodes[ $from ], $nodes[ $to ] ) ) {
				continue;
			}
			$normalized = self::edge( $from, $to, sanitize_key( (string) $edge['type'] ), isset( $edge['weight'] ) ? (float) $edge['weight'] : 1.0 );
			if ( isset( $edges[ $normalized['id'] ] ) ) {
				continue;
			}
			$edges[ $normalized['id'] ] = $normalized;
		}

		return array(
			'version'      => self::SCHEMA_VERSION,
			'source'       => $source_slug,
			'generated_at' => gmdate( 'c' ),
			'nodes'        => array_values( $nodes ),
			'edges'        => array_values( $edges ),
			'types'        => self::normalize_types( isset( $raw['types'] ) && is_array( $raw['types'] ) ? $raw['types'] : array() ),
			'stats'        => array(
				'nodes' => count( $nodes ),
				'edges' => count( $edges ),
			),
		);
	}

	/**
	 * Plain text for labels: no tags, no HTML entities (renderers escape on output).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain_text( string $text ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * @param array<string, mixed> $types Raw types map.
	 * @return array<string, array<string, array<string, string>>>
	 */
	private static function normalize_types( array $types ): array {
		$out = array(
			'nodes' => array(),
			'edges' => array(),
		);
		foreach ( array( 'nodes', 'edges' ) as $group ) {
			if ( empty( $types[ $group ] ) || ! is_array( $types[ $group ] ) ) {
				continue;
			}
			foreach ( $types[ $group ] as $slug => $def ) {
				$def  = is_array( $def ) ? $def : array();
				$role = isset( $def['role'] ) ? sanitize_key( (string) $def['role'] ) : '';

				$out[ $group ][ sanitize_key( (string) $slug ) ] = array(
					'label' => isset( $def['label'] ) ? self::plain_text( (string) $def['label'] ) : (string) $slug,
					// group = classifier (category, tag, project); item = record; context = where items are used.
					'role'  => in_array( $role, array( 'group', 'item', 'context' ), true ) ? $role : 'item',
					// Styling input only. The layer never derives meaning from color.
					'color' => isset( $def['color'] ) ? (string) sanitize_hex_color( (string) $def['color'] ) : '',
				);
			}
		}
		return $out;
	}
}

<?php
/**
 * Graph analysis on the neutral contract. Domain-agnostic: works on roles, not on FAQ terms.
 *
 * Adds to the contract:
 * - nodes[].degree
 * - stats.hubs        most connected nodes
 * - stats.orphans     nodes with no edges
 * - stats.weak_groups group-role nodes with too few members (e.g. dead categories/tags)
 * - stats.components  connected clusters
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Analyzer {

	public const LIST_LIMIT = 50;

	public static function init(): void {
		add_filter( 'forwp_graph_built', array( __CLASS__, 'analyze' ), 10, 2 );
	}

	/**
	 * @param array<string, mixed> $graph       Normalized contract.
	 * @param string               $source_slug Source slug.
	 * @return array<string, mixed>
	 */
	public static function analyze( $graph, $source_slug = '' ) {
		if ( ! is_array( $graph ) || empty( $graph['nodes'] ) ) {
			return $graph;
		}

		$node_roles = self::roles( $graph );
		$degree     = array();
		$members    = array();
		$parent     = array();

		foreach ( $graph['nodes'] as $node ) {
			$degree[ $node['id'] ]  = 0;
			$members[ $node['id'] ] = 0;
			$parent[ $node['id'] ]  = $node['id'];
		}

		$has_group    = array();
		$has_context  = array();
		$has_children = array();

		$role_of = array();
		foreach ( $graph['nodes'] as $node ) {
			$role_of[ $node['id'] ] = isset( $node_roles[ $node['type'] ] ) ? $node_roles[ $node['type'] ] : 'item';
		}

		foreach ( $graph['edges'] as $edge ) {
			$from = $edge['source'];
			$to   = $edge['target'];
			++$degree[ $from ];
			++$degree[ $to ];

			// Membership: a non-group node attached to a group node.
			if ( 'group' === $role_of[ $to ] && 'group' !== $role_of[ $from ] ) {
				++$members[ $to ];
			} elseif ( 'group' === $role_of[ $from ] && 'group' !== $role_of[ $to ] ) {
				++$members[ $from ];
			}

			// Group hierarchy: group -> group edges point child -> parent (contract rule).
			if ( 'group' === $role_of[ $from ] && 'group' === $role_of[ $to ] ) {
				$has_children[ $to ] = true;
			}

			// Item coverage: is the item classified, and is it used anywhere.
			foreach ( array( array( $from, $to ), array( $to, $from ) ) as $pair ) {
				if ( 'item' !== $role_of[ $pair[0] ] ) {
					continue;
				}
				if ( 'group' === $role_of[ $pair[1] ] ) {
					$has_group[ $pair[0] ] = true;
				} elseif ( 'context' === $role_of[ $pair[1] ] ) {
					$has_context[ $pair[0] ] = true;
				}
			}

			self::union( $parent, $from, $to );
		}

		$hubs        = array();
		$orphans     = array();
		$weak_groups = array();

		/** @param int $threshold Group with this many members or fewer is weak. */
		$weak_threshold = (int) apply_filters( 'forwp_graph_weak_group_threshold', 1, $source_slug );

		$roles_present = array_flip( array_values( $node_roles ) );
		$no_group      = 0;
		$no_context    = 0;

		foreach ( $graph['nodes'] as $i => $node ) {
			$id                              = $node['id'];
			$graph['nodes'][ $i ]['degree']  = $degree[ $id ];
			$graph['nodes'][ $i ]['members'] = $members[ $id ];

			$flags = array();
			if ( 0 === $degree[ $id ] ) {
				$flags[] = 'orphan';
			}
			if ( self::is_weak_group( $role_of[ $id ], $members[ $id ], ! empty( $has_children[ $id ] ), $weak_threshold ) ) {
				$flags[] = 'weak';
			}
			if ( 'item' === $role_of[ $id ] ) {
				if ( isset( $roles_present['group'] ) && empty( $has_group[ $id ] ) ) {
					$flags[] = 'no_group';
					++$no_group;
				}
				if ( isset( $roles_present['context'] ) && empty( $has_context[ $id ] ) ) {
					$flags[] = 'no_context';
					++$no_context;
				}
			}
			$graph['nodes'][ $i ]['flags'] = $flags;

			$brief = array(
				'id'     => $id,
				'type'   => $node['type'],
				'label'  => $node['label'],
				'url'    => $node['url'],
				'degree' => $degree[ $id ],
			);

			if ( 0 === $degree[ $id ] ) {
				$orphans[] = $brief;
			} elseif ( $degree[ $id ] >= 2 ) {
				$hubs[] = $brief;
			}

			if ( self::is_weak_group( $role_of[ $id ], $members[ $id ], ! empty( $has_children[ $id ] ), $weak_threshold ) ) {
				$brief['members'] = $members[ $id ];
				$weak_groups[]    = $brief;
			}
		}

		usort(
			$hubs,
			static function ( $a, $b ) {
				return $b['degree'] <=> $a['degree'];
			}
		);

		// Top hubs per node type, so high-degree groups don't hide item hubs.
		$per_type = array();
		foreach ( $hubs as $hub ) {
			$count = isset( $per_type[ $hub['type'] ] ) ? count( $per_type[ $hub['type'] ] ) : 0;
			if ( $count < 10 ) {
				$per_type[ $hub['type'] ][] = $hub;
			}
		}
		$hubs = $per_type ? array_merge( ...array_values( $per_type ) ) : array();

		$hub_ids = array_flip( wp_list_pluck( $hubs, 'id' ) );
		foreach ( $graph['nodes'] as $i => $node ) {
			if ( isset( $hub_ids[ $node['id'] ] ) ) {
				$graph['nodes'][ $i ]['flags'][] = 'hub';
			}
		}

		$sizes = array();
		foreach ( array_keys( $parent ) as $id ) {
			$root           = self::find( $parent, $id );
			$sizes[ $root ] = isset( $sizes[ $root ] ) ? $sizes[ $root ] + 1 : 1;
		}
		$clusters = array_filter(
			$sizes,
			static function ( $size ) {
				return $size > 1;
			}
		);

		$graph['stats'] = array_merge(
			isset( $graph['stats'] ) && is_array( $graph['stats'] ) ? $graph['stats'] : array(),
			array(
				'hubs'        => $hubs,
				'orphans'     => array_slice( $orphans, 0, self::LIST_LIMIT ),
				'weak_groups' => array_slice( $weak_groups, 0, self::LIST_LIMIT ),
				'totals'      => array(
					'orphans'     => count( $orphans ),
					'weak_groups' => count( $weak_groups ),
					'no_group'    => $no_group,
					'no_context'  => $no_context,
				),
				'components'  => array(
					'clusters' => count( $clusters ),
					'largest'  => $clusters ? max( $clusters ) : 0,
					'isolated' => count( $sizes ) - count( $clusters ),
				),
			)
		);

		return $graph;
	}

	/**
	 * Weak group: too few members and no subgroups (a parent group is a container, not dead).
	 *
	 * @param string $role         Node role.
	 * @param int    $members      Direct members.
	 * @param bool   $has_children Has child groups.
	 * @param int    $threshold    Max members to count as weak.
	 * @return bool
	 */
	private static function is_weak_group( string $role, int $members, bool $has_children, int $threshold ): bool {
		return 'group' === $role && ! $has_children && $members <= $threshold;
	}

	/**
	 * @param array<string, mixed> $graph Contract.
	 * @return array<string, string> node type => role
	 */
	private static function roles( array $graph ): array {
		$out = array();
		if ( ! empty( $graph['types']['nodes'] ) && is_array( $graph['types']['nodes'] ) ) {
			foreach ( $graph['types']['nodes'] as $slug => $def ) {
				$out[ $slug ] = isset( $def['role'] ) ? $def['role'] : 'item';
			}
		}
		return $out;
	}

	/**
	 * @param array<string, string> $parent Union-find parents (by reference).
	 * @param string                $id     Node id.
	 * @return string Root id.
	 */
	private static function find( array &$parent, string $id ): string {
		while ( $parent[ $id ] !== $id ) {
			$parent[ $id ] = $parent[ $parent[ $id ] ];
			$id            = $parent[ $id ];
		}
		return $id;
	}

	/**
	 * @param array<string, string> $parent Union-find parents (by reference).
	 * @param string                $a      Node id.
	 * @param string                $b      Node id.
	 */
	private static function union( array &$parent, string $a, string $b ): void {
		$root_a = self::find( $parent, $a );
		$root_b = self::find( $parent, $b );
		if ( $root_a !== $root_b ) {
			$parent[ $root_b ] = $root_a;
		}
	}
}

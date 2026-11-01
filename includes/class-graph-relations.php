<?php
/**
 * FAQ relations for the graph layer: nodes, edges and their types.
 *
 * Domain lives here, in the plugin — not in graph/. The layer only receives the contract.
 * Loaded on 'forwp_graph_layer_loaded' (see graph-relations.php).
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

use ForWP\FAQ\Integrations\Polylang;
use ForWP\Graph\Contract;
use ForWP\Graph\Contracts\Relation_Source_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Graph_Relations implements Relation_Source_Interface {

	public const SLUG = 'faq';

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return __( 'FAQ relations', '4wp-faq' );
	}

	public function is_ready(): bool {
		return Settings::is_graph_enabled()
			&& Settings::is_setup_complete()
			&& post_type_exists( Settings::get_post_type() )
			&& taxonomy_exists( Settings::get_taxonomy() );
	}

	public function get_rest_namespace(): string {
		return 'forwp-faq/v1';
	}

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_args_schema(): array {
		return array(
			'category'      => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'include_pages' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'lang'          => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	public function get_cache_version(): string {
		return implode(
			'|',
			array(
				(string) get_option( 'forwp_faq_last_scan_at', 0 ),
				(string) wp_cache_get_last_changed( 'posts' ),
				(string) wp_cache_get_last_changed( 'terms' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $args Sanitized args.
	 * @return array<string, mixed>
	 */
	public function build( array $args ) {
		$lang = isset( $args['lang'] ) ? sanitize_key( (string) $args['lang'] ) : '';

		return Polylang::with_language(
			'' === $lang ? null : $lang,
			function () use ( $args ) {
				return $this->collect( $args );
			}
		);
	}

	/**
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>
	 */
	private function collect( array $args ): array {
		$taxonomy      = Settings::get_taxonomy();
		$category      = isset( $args['category'] ) ? absint( $args['category'] ) : 0;
		$include_pages = ! isset( $args['include_pages'] ) || (bool) $args['include_pages'];

		$nodes = array();
		$edges = array();

		// 1. Categories (+ hierarchy).
		$term_args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		);
		if ( $category ) {
			$term_args['include'] = array_merge( array( $category ), get_term_children( $category, $taxonomy ) );
		}
		$terms = get_terms( $term_args );
		$terms = is_wp_error( $terms ) ? array() : $terms;

		foreach ( $terms as $term ) {
			$nodes[] = Contract::node(
				'term:' . $term->term_id,
				'category',
				$term->name,
				array(
					'url'    => (string) get_edit_term_link( $term->term_id, $taxonomy ),
					'weight' => (int) $term->count,
					'meta'   => array( 'slug' => $term->slug ),
				)
			);
			if ( $term->parent ) {
				$edges[] = Contract::edge( 'term:' . $term->term_id, 'term:' . $term->parent, 'child_of' );
			}
		}

		// 2. FAQ entries (registry, published — same scope as dashboard stats).
		$query_args = array(
			'post_type'              => Settings::get_post_type(),
			'post_status'            => 'publish',
			'posts_per_page'         => Contract::MAX_NODES,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
			'orderby'                => 'title',
			'order'                  => 'ASC',
		);
		if ( $category ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy'         => $taxonomy,
					'terms'            => $category,
					'include_children' => true,
				),
			);
		}
		$entries = get_posts( $query_args );

		$used_ids = array();

		foreach ( $entries as $entry ) {
			$used_in = get_post_meta( $entry->ID, 'used_in_posts', true );
			$used_in = is_array( $used_in ) ? array_values( array_unique( array_map( 'absint', $used_in ) ) ) : array();

			$nodes[] = Contract::node(
				'faq:' . $entry->ID,
				'entry',
				get_the_title( $entry ),
				array(
					'url'    => (string) get_edit_post_link( $entry->ID, 'raw' ),
					'weight' => count( $used_in ),
				)
			);

			$entry_terms = get_the_terms( $entry, $taxonomy );
			foreach ( is_array( $entry_terms ) ? $entry_terms : array() as $term ) {
				$edges[] = Contract::edge( 'faq:' . $entry->ID, 'term:' . $term->term_id, 'in_category' );
			}

			if ( $include_pages ) {
				foreach ( $used_in as $post_id ) {
					if ( $post_id ) {
						$edges[]              = Contract::edge( 'faq:' . $entry->ID, 'post:' . $post_id, 'used_in' );
						$used_ids[ $post_id ] = true;
					}
				}
			}
		}

		// 3. Content where entries are used ("Used in").
		if ( $used_ids ) {
			$ids = array_keys( $used_ids );
			_prime_post_caches( $ids, false, false );

			foreach ( $ids as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post || 'trash' === $post->post_status ) {
					continue;
				}
				$nodes[] = Contract::node(
					'post:' . $post_id,
					'content',
					get_the_title( $post ),
					array(
						'url'  => (string) get_edit_post_link( $post_id, 'raw' ),
						'meta' => array( 'post_type' => $post->post_type ),
					)
				);
			}
		}

		return array(
			'nodes' => $nodes,
			'edges' => $edges,
			'types' => array(
				'nodes' => array(
					'entry'    => array(
						'label' => __( 'Question', '4wp-faq' ),
						'role'  => 'item',
					),
					'category' => array(
						'label' => __( 'Category', '4wp-faq' ),
						'role'  => 'group',
					),
					'content'  => array(
						'label' => __( 'Page', '4wp-faq' ),
						'role'  => 'context',
					),
				),
				'edges' => array(
					'in_category' => array( 'label' => __( 'In category', '4wp-faq' ) ),
					'child_of'    => array( 'label' => __( 'Subcategory of', '4wp-faq' ) ),
					'used_in'     => array( 'label' => __( 'Used in', '4wp-faq' ) ),
				),
			),
		);
	}
}

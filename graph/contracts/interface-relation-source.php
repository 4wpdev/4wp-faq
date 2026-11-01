<?php
/**
 * Relation source: maps one plugin's domain data to the neutral graph contract.
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Relation_Source_Interface {

	/**
	 * Unique slug, e.g. 'faq'. Used as node id prefix scope and cache key.
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Human label (translated by the plugin that owns the source).
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Whether the domain has data to build from (e.g. FAQ registry enabled).
	 *
	 * @return bool
	 */
	public function is_ready(): bool;

	/**
	 * REST namespace of the owning plugin, e.g. 'forwp-faq/v1'. Route: {ns}/graph.
	 *
	 * @return string
	 */
	public function get_rest_namespace(): string;

	/**
	 * Capability required to read this graph.
	 *
	 * @return string
	 */
	public function get_capability(): string;

	/**
	 * Allowed request args: name => [ 'type' => 'string|integer|boolean', 'default' => mixed ].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_args_schema(): array;

	/**
	 * Build raw graph: [ 'nodes' => [], 'edges' => [], 'types' => [ 'nodes' => [], 'edges' => [] ] ].
	 * Contract::normalize() validates the result.
	 *
	 * @param array<string, mixed> $args Sanitized args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function build( array $args );

	/**
	 * Opaque string that changes when domain data changes. Drives cache invalidation.
	 *
	 * @return string
	 */
	public function get_cache_version(): string;
}

<?php
/**
 * Renderer: draws the neutral graph contract with one JS library.
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Renderer_Interface {

	/**
	 * @return string e.g. 'cytoscape'.
	 */
	public function get_slug(): string;

	/**
	 * @return string Library name (proper noun, not translated).
	 */
	public function get_label(): string;

	/**
	 * @return bool False for planned renderers.
	 */
	public function is_implemented(): bool;

	/**
	 * Register vendor + adapter scripts. Bundled locally, never from a CDN.
	 */
	public function register_assets(): void;

	/**
	 * Script handle to enqueue / depend on. Empty when not implemented.
	 *
	 * @return string
	 */
	public function get_script_handle(): string;
}

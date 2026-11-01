<?php
/**
 * Cytoscape.js renderer (default). Library is bundled in graph/assets/vendor — never loaded from a CDN.
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph\Renderers;

use ForWP\Graph\Contracts\Renderer_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cytoscape_Renderer implements Renderer_Interface {

	public const SLUG          = 'cytoscape';
	public const VENDOR_FILE   = 'assets/vendor/cytoscape.min.js';
	public const VENDOR_HANDLE = 'forwp-graph-cytoscape';

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'Cytoscape.js';
	}

	/**
	 * Implemented once the bundled library ships with the layer (Stage 3).
	 */
	public function is_implemented(): bool {
		return is_readable( FORWP_GRAPH_LAYER_DIR . self::VENDOR_FILE );
	}

	public function register_assets(): void {
		if ( ! $this->is_implemented() ) {
			return;
		}
		wp_register_script(
			self::VENDOR_HANDLE,
			FORWP_GRAPH_LAYER_URL . self::VENDOR_FILE,
			array(),
			FORWP_GRAPH_LAYER_VERSION,
			true
		);
	}

	public function get_script_handle(): string {
		return $this->is_implemented() ? self::VENDOR_HANDLE : '';
	}
}

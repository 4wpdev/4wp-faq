<?php
/**
 * Planned renderer (listed, not selectable).
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph\Renderers;

use ForWP\Graph\Contracts\Renderer_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Placeholder_Renderer implements Renderer_Interface {

	/** @var string */
	private $slug;

	/** @var string */
	private $label;

	/**
	 * @param string $slug  Slug.
	 * @param string $label Library name.
	 */
	public function __construct( string $slug, string $label ) {
		$this->slug  = $slug;
		$this->label = $label;
	}

	public function get_slug(): string {
		return $this->slug;
	}

	public function get_label(): string {
		return $this->label;
	}

	public function is_implemented(): bool {
		return false;
	}

	public function register_assets(): void {}

	public function get_script_handle(): string {
		return '';
	}
}

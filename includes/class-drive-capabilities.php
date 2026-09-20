<?php
/**
 * Drive allow-list owned by 4WP FAQ — not Drive's static catalog.
 *
 * H2 aliases live in Drive Patterns after import, not in PHP.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Only the forwp/faq wrapper. List / Categories / Count are not import wraps.
 */
class Drive_Capabilities {
	public static function init() {
		add_filter( 'forwp_drive_wrap_capabilities', [ self::class, 'register' ] );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function export() {
		return [
			[
				'id'            => '4wp-faq',
				'origin'        => 'family',
				'plugin'        => '4wp-faq',
				'block'         => Plugin::BLOCK_NAME,
				'label'         => '4WP FAQ',
				'maps'          => [],
				'wrap'          => 'faq-qa',
				'match'         => [
					'type'    => 'heading',
					'level'   => 2,
					'pattern' => '',
				],
				'heading_seeds' => 'FAQ, Frequently Asked Questions, Часті питання',
				'prompt'        => 'H2 = FAQ section (any language — set the title in Drive Patterns). Each H3 is a question; paragraphs and lists under it are the answer. Pin the wrap in Incoming to remap.',
				'auto'          => false,
				'manual'        => true,
			],
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $items Drive catalog.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register( array $items ) {
		foreach ( self::export() as $row ) {
			$items[] = $row;
		}

		return $items;
	}

	public static function has_faq_blocks( $content ) {
		return false !== strpos( (string) $content, 'wp:forwp/faq' );
	}
}

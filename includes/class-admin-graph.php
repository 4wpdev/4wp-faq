<?php
/**
 * Graph page in the FAQ menu: relations as diagrams (admin only).
 *
 * The page and its texts belong to FAQ. Rendering comes from the shared graph layer.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Graph {

	public const PAGE_SLUG = 'forwp-faq-graph';

	/** @var string */
	private static $hook = '';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 21 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Graph is available: setup done, enabled, layer loaded, source registered.
	 */
	public static function is_available(): bool {
		return Settings::is_setup_complete()
			&& Settings::is_graph_enabled()
			&& class_exists( \ForWP\Graph\Admin_View::class )
			&& null !== \ForWP\Graph\Source_Registry::get( Graph_Relations::SLUG );
	}

	public static function get_page_url(): string {
		return admin_url( 'edit.php?post_type=' . rawurlencode( Settings::get_post_type() ) . '&page=' . self::PAGE_SLUG );
	}

	public static function register_menu(): void {
		if ( ! self::is_available() ) {
			return;
		}

		$hook = add_submenu_page(
			'edit.php?post_type=' . Settings::get_post_type(),
			__( 'FAQ Graph', '4wp-faq' ),
			__( 'Graph', '4wp-faq' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);

		self::$hook = is_string( $hook ) ? $hook : '';
	}

	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Access denied.', '4wp-faq' ) );
		}

		echo '<div class="wrap forwp-faq-graph-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'FAQ Graph', '4wp-faq' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'How questions, categories and pages connect. Find hubs, weak categories, questions without a category and questions not used anywhere.', '4wp-faq' ) . '</p>';

		\ForWP\Graph\Admin_View::render( Graph_Relations::SLUG, __( 'The graph needs JavaScript.', '4wp-faq' ) );

		echo '</div>';
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue( $hook_suffix ): void {
		if ( '' === self::$hook || $hook_suffix !== self::$hook ) {
			return;
		}

		$source = \ForWP\Graph\Source_Registry::get( Graph_Relations::SLUG );
		if ( ! $source ) {
			return;
		}

		\ForWP\Graph\Admin_View::enqueue(
			$source,
			array(
				'groupArg' => 'category',
				'i18n'     => self::texts(),
			)
		);
	}

	/**
	 * UI texts in the FAQ text domain.
	 *
	 * @return array<string, string>
	 */
	private static function texts(): array {
		return array(
			'sliceAll'        => __( 'Everything', '4wp-faq' ),
			'sliceGroups'     => __( 'Categories & questions', '4wp-faq' ),
			'sliceContext'    => __( 'Questions & pages', '4wp-faq' ),
			'sliceIssues'     => __( 'Issues', '4wp-faq' ),
			'slice'           => __( 'View', '4wp-faq' ),
			'groupFilter'     => __( 'Category', '4wp-faq' ),
			'allGroups'       => __( 'All categories', '4wp-faq' ),
			'layout'          => __( 'Layout', '4wp-faq' ),
			'layoutClusters'  => __( 'Clusters', '4wp-faq' ),
			'layoutRings'     => __( 'Rings by links', '4wp-faq' ),
			'layoutTree'      => __( 'Tree from categories', '4wp-faq' ),
			'layoutGrid'      => __( 'Grid', '4wp-faq' ),
			'search'          => __( 'Search questions, categories, pages', '4wp-faq' ),
			'fit'             => __( 'Fit', '4wp-faq' ),
			'refresh'         => __( 'Refresh', '4wp-faq' ),
			'loading'         => __( 'Building graph…', '4wp-faq' ),
			'error'           => __( 'Could not build the graph.', '4wp-faq' ),
			'canvasLabel'     => __( 'Relations graph of FAQ questions, categories and pages', '4wp-faq' ),
			'items'           => __( 'Questions', '4wp-faq' ),
			'groups'          => __( 'Categories', '4wp-faq' ),
			'contexts'        => __( 'Pages', '4wp-faq' ),
			'links'           => __( 'Links', '4wp-faq' ),
			'weakGroups'      => __( 'Weak categories', '4wp-faq' ),
			'weakGroupsHint'  => __( '1 question or none', '4wp-faq' ),
			'noGroup'         => __( 'No category', '4wp-faq' ),
			'noContext'       => __( 'Not used on pages', '4wp-faq' ),
			'clusters'        => __( 'Clusters', '4wp-faq' ),
			/* translators: %d: number of nodes */
			'largest'         => __( 'Largest: %d', '4wp-faq' ),
			'panelHint'       => __( 'Click a node to see its links. Double-click opens it for editing.', '4wp-faq' ),
			/* translators: %d: number of links */
			'linksCount'      => __( '%d links', '4wp-faq' ),
			'edit'            => __( 'Edit', '4wp-faq' ),
			'flagHub'         => __( 'Hub', '4wp-faq' ),
			'flagWeak'        => __( 'Weak category', '4wp-faq' ),
			'flagNoGroup'     => __( 'No category', '4wp-faq' ),
			'flagNoContext'   => __( 'Not used on pages', '4wp-faq' ),
			'flagOrphan'      => __( 'Orphan', '4wp-faq' ),
			'legendIssue'     => __( 'Needs attention', '4wp-faq' ),
			'legendSize'      => __( 'Size = number of links', '4wp-faq' ),
			'chartCoverage'   => __( 'Question coverage', '4wp-faq' ),
			'coverageBoth'    => __( 'In a category and used', '4wp-faq' ),
			'coverageGroup'   => __( 'In a category, not used', '4wp-faq' ),
			'coverageContext' => __( 'Used, no category', '4wp-faq' ),
			'coverageNone'    => __( 'Neither', '4wp-faq' ),
			'chartGroups'     => __( 'Questions per category', '4wp-faq' ),
			'chartContexts'   => __( 'Pages with the most FAQ links', '4wp-faq' ),
		);
	}
}

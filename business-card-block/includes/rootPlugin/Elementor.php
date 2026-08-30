<?php
/**
 * Elementor integration.
 *
 * Kept entirely in this layer: nothing in the block code knows Elementor
 * exists. Every hook is registered behind an "is Elementor actually loaded"
 * check, so the file is inert — never fatal — on sites without it.
 *
 * @package BusinessCardBlock
 */

namespace BCB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor {

	const CATEGORY = 'business-card-block';

	function __construct() {
		// elementor/loaded fires during plugins_loaded. Registering the hooks
		// from inside it means they are only ever added when Elementor is
		// present and initialised.
		add_action( 'elementor/loaded', array( $this, 'boot' ) );
	}

	/**
	 * Register the widget, its category and its preview bridge.
	 *
	 * @return void
	 */
	function boot() {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_preview_bridge' ) );
	}

	/**
	 * Add a Business Cards panel category.
	 *
	 * @param \Elementor\Elements_Manager $manager Elementor's category manager.
	 * @return void
	 */
	function register_category( $manager ) {
		$manager->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'Business Cards', 'business-card-block' ),
				'icon'  => 'eicon-person',
			)
		);
	}

	/**
	 * Register the widget class.
	 *
	 * @param \Elementor\Widgets_Manager $manager Elementor's widget manager.
	 * @return void
	 */
	function register_widget( $manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once BCB_DIR_PATH . 'includes/rootPlugin/elementor/CardWidget.php';

		$manager->register( new \BCB\Elementor\CardWidget() );
	}

	/**
	 * Re-mount cards that Elementor injects into the editor canvas.
	 *
	 * The view script hydrates on DOMContentLoaded, which has long passed by
	 * the time the editor swaps in a re-rendered widget.
	 *
	 * @return void
	 */
	function enqueue_preview_bridge() {
		$handle = self::view_script_handle();

		if ( ! $handle ) {
			return;
		}

		wp_enqueue_script( $handle );
		wp_add_inline_script(
			$handle,
			"window.addEventListener('elementor/frontend/init',function(){"
			. "if(!window.elementorFrontend||!window.elementorFrontend.hooks){return;}"
			. "elementorFrontend.hooks.addAction('frontend/element_ready/bcb-business-card.default',function(\$scope){"
			. "if(window.bcbMountCards&&\$scope&&\$scope[0]){window.bcbMountCards(\$scope[0]);}"
			. "});});"
		);
	}

	/**
	 * The registered handle of the core card block's view script.
	 *
	 * Read from the block registry rather than hardcoded, so it keeps working
	 * if WordPress changes how block asset handles are generated.
	 *
	 * @return string
	 */
	static function view_script_handle() {
		$block = \WP_Block_Type_Registry::get_instance()->get_registered( 'business/card' );

		if ( ! $block || empty( $block->view_script_handles ) ) {
			return '';
		}

		return reset( $block->view_script_handles );
	}

	/**
	 * Enqueue the card's frontend script and styles.
	 *
	 * Called from the widget's render, so the assets only load on pages that
	 * actually contain a card widget.
	 *
	 * @return void
	 */
	static function enqueue_card_assets() {
		$block = \WP_Block_Type_Registry::get_instance()->get_registered( 'business/card' );

		if ( ! $block ) {
			return;
		}

		foreach ( (array) $block->view_script_handles as $handle ) {
			wp_enqueue_script( $handle );
		}

		foreach ( (array) $block->style_handles as $handle ) {
			wp_enqueue_style( $handle );
		}
	}
}

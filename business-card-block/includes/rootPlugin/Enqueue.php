<?php

namespace BCB;

class Enqueue {
    function __construct() {
        add_action( 'enqueue_block_assets', [$this, 'enqueueBlockAssets'] );
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
        add_action( 'admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
        add_filter( 'block_editor_settings_all', [$this, 'injectEditorIframeStyles'], 10, 2 );
    }

    /**
     * The shortcode clip + chooser render inside the block-editor canvas, which
     * is an <iframe>. Styles enqueued on the outer document don't reach it, so
     * push the chooser stylesheet into the iframe via the editor "styles"
     * setting — scoped to the Business Card CPT only.
     */
    function injectEditorIframeStyles( $settings, $context ) {
        if ( empty( $context->post ) || 'bcb' !== $context->post->post_type ) {
            return $settings;
        }

        $css_path = BCB_DIR_PATH . 'build/admin-block-chooser.css';
        if ( file_exists( $css_path ) ) {
            $css = file_get_contents( $css_path );
            if ( $css ) {
                $settings['styles'][] = [ 'css' => $css ];
            }
        }

        return $settings;
    }

    
	function enqueueBlockAssets(){
		wp_register_style( 'fontAwesome', BCB_DIR_URL . 'public/css/font-awesome.min.css', [], '6.4.2' );
	}
	
	
    function enqueueBlockEditorAssets(){
	    $bcb_is_premium_inline = 'window.bcbIsPremium = ' . wp_json_encode( bcbIsPremium() ) . '; var bcbIsPremium = window.bcbIsPremium;';
	    wp_add_inline_script('business-card-editor-script', $bcb_is_premium_inline, 'before');

	    // Contact-type table (labels, link templates, default glyphs). PHP owns
	    // the link formats; the editor only reads them, so a URL scheme is
	    // never written down in two places.
	    $bcb_contact_types = 'window.bcbContactTypes = ' . wp_json_encode( \BCB\Card\Contacts::js_table() ) . ';';
	    wp_add_inline_script('business-card-editor-script', $bcb_contact_types, 'before');

	    // Theme table with the Pro flag. The editor reads this rather than
	    // keeping a second copy of which designs are premium.
	    $bcb_themes = 'window.bcbThemes = ' . wp_json_encode( \BCB\Card\Themes::js_table() ) . ';';
	    wp_add_inline_script('business-card-editor-script', $bcb_themes, 'before');

	    // Template chooser: only on the Business Card CPT editor (post-new.php / post.php).
	    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
	    if ($screen && 'bcb' === $screen->post_type) {
	        $asset = require BCB_DIR_PATH . 'build/admin-block-chooser.asset.php';
	        wp_enqueue_script(
	            'bcb-block-chooser',
	            BCB_DIR_URL . 'build/admin-block-chooser.js',
	            $asset['dependencies'],
	            $asset['version'],
	            true
	        );
	        wp_enqueue_style(
	            'bcb-block-chooser',
	            BCB_DIR_URL . 'build/admin-block-chooser.css',
	            [],
	            $asset['version']
	        );
	    }
    }
	
   

    function adminEnqueueScripts($screen){
        global $typenow;

        // Assets for the Business Card CPT screens (post list/edit): shortcode-copy helper, etc.
        if ('bcb' === $typenow) {
            wp_enqueue_style('bcb-admin-post', BCB_DIR_URL . 'build/admin-post.css', [], BCB_PLUGIN_VERSION);
			wp_enqueue_script('bcb-admin-post', BCB_DIR_URL . 'build/admin-post.js', [], BCB_PLUGIN_VERSION, true);
        }

        // Help & Demos dashboard React app. The submenu lives under the `bcb` CPT, so it can be
        // reached as either edit.php?post_type=bcb&page=bcb-dashboard or admin.php?page=bcb-dashboard.
        // Gate on the unique page slug so the assets load regardless of the entry URL (the previous
        // `$typenow === 'bcb'` check failed for the admin.php form, leaving the page blank).
        // Use sanitize_text_field (not sanitize_key) to preserve the slug's uppercase `D`.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check, no state change.
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if ('bcb-dashboard' === $page) {
            wp_enqueue_style('bcb-admin-style', BCB_DIR_URL . 'build/admin-dashboard.css', false, BCB_PLUGIN_VERSION);

            // Read dependencies/version from the build manifest so externals like wp-api-fetch are
            // always loaded (the old hardcoded list omitted it, which could break the bundle at runtime).
            $asset = require BCB_DIR_PATH . 'build/admin-dashboard.asset.php';
            wp_enqueue_script(
                'bcb-admin-script',
                BCB_DIR_URL . 'build/admin-dashboard.js',
                array_merge($asset['dependencies'], ["wp-util"]),
                $asset['version'],
                true
            );
        }
    }
  
}

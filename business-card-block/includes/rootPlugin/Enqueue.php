<?php

namespace BCB;

class Enqueue {
    function __construct() {
        add_action( 'enqueue_block_assets', [$this, 'enqueueBlockAssets'] );
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
        add_action( 'admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
    }

    
	function enqueueBlockAssets(){
		wp_register_style( 'fontAwesome', BCB_DIR_URL . 'public/css/font-awesome.min.css', [], '6.4.2' );
	}
	
	
    function enqueueBlockEditorAssets(){
	    $bcb_is_premium_inline = 'window.bcbIsPremium = ' . wp_json_encode( bcbIsPremium() ) . '; var bcbIsPremium = window.bcbIsPremium;';
	    wp_add_inline_script('business-card-editor-script', $bcb_is_premium_inline, 'before');
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
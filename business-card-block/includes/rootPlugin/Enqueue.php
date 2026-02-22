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
	    wp_add_inline_script('business-card-editor-script', 'const bcbIsPremium = ' . wp_json_encode(bcbIsPremium()) . ';', 'before');
    }
	
   

    function adminEnqueueScripts($screen){
        global $typenow;
        
        if ('bcb' === $typenow) {
            wp_enqueue_style('bcb-admin-post', BCB_DIR_URL . 'build/admin-post.css', [], BCB_PLUGIN_VERSION);
			wp_enqueue_script('bcb-admin-post', BCB_DIR_URL . 'build/admin-post.js', [], BCB_PLUGIN_VERSION, true);
         

            if ($screen === "bcb_page_bcb-dashboard") {
				wp_enqueue_style('bcb-admin-style', BCB_DIR_URL . 'build/admin-dashboard.css', false, BCB_PLUGIN_VERSION);
                wp_enqueue_script('bcb-admin-script', BCB_DIR_URL . 'build/admin-dashboard.js', ['react', 'react-dom', 'wp-data', "wp-api", "wp-util", "wp-i18n"], BCB_PLUGIN_VERSION, true);
            }

        }
    }
  
}
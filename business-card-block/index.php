<?php

/**
 * Plugin Name: Business Card Block
 * Description: Show your business card in web.
 * Version: 2.0.3
 * Author: bPlugins
 * Author URI: https://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: business-card
 */
// ABS PATH
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'bcb_fs' ) ) {
    bcb_fs()->set_basename( false, __FILE__ );
} else {
    // Constant
    define( 'BCB_PLUGIN_VERSION', ( isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '2.0.3' ) );
    define( 'BCB_DIR_URL', plugin_dir_url( __FILE__ ) );
    define( 'BCB_DIR_PATH', plugin_dir_path( __FILE__ ) );
    define( 'BCB_HAS_PRO', file_exists( dirname( __FILE__ ) . '/vendor/freemius/start.php' ) );
    if ( !function_exists( 'bcb_fs' ) ) {
        function bcb_fs() {
            global $bcb_fs;
            if ( !isset( $bcb_fs ) ) {
                if ( BCB_HAS_PRO ) {
                    require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
                } else {
                    require_once dirname( __FILE__ ) . '/vendor/freemius-lite/start.php';
                }
                $bcbConfig = array(
                    'id'                  => '21894',
                    'slug'                => 'business-card-block',
                    'premium_slug'        => 'business-card-block-pro',
                    'type'                => 'plugin',
                    'public_key'          => 'pk_36bb83dedb8aa937a82cf66399ff4',
                    'is_premium'          => false,
                    'premium_suffix'      => 'Pro',
                    'has_premium_version' => true,
                    'has_addons'          => false,
                    'has_paid_plans'      => true,
                    'menu'                => array(
                        'slug'       => 'edit.php?post_type=bcb',
                        'first-path' => 'edit.php?post_type=bcb&page=bcb-dashboard',
                        'support'    => false,
                    ),
                );
                $bcb_fs = ( BCB_HAS_PRO ? fs_dynamic_init( $bcbConfig ) : fs_lite_dynamic_init( $bcbConfig ) );
            }
            return $bcb_fs;
        }

        bcb_fs();
        do_action( 'bcb_fs_loaded' );
    }
    require_once BCB_DIR_PATH . 'includes/utils/functions.php';
    require_once BCB_DIR_PATH . 'includes/plugin.php';
}
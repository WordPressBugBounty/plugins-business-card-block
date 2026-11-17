<?php

/**
 * Plugin Name: Business Card Block
 * Description: Show your business card in web.
 * Version:2.1.9
 * Author: bPlugins
 * Author URI: https://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: business-card-block
 */
// ABS PATH
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'bcb_fs' ) ) {
    bcb_fs()->set_basename( false, __FILE__ );
} else {
    // Constant
    define( 'BCB_PLUGIN_VERSION', ( isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '2.1.9' ) );
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
    require_once plugin_dir_path( __FILE__ ) . 'includes/bplugins-bfcm2026-promo.php';
    add_action( 'admin_notices', function () {
        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            // Write to the debug log (wp-content/debug.log) – you can view it via the Debug Log plugin or in the server file.
            error_log( '🛠️ Current admin screen ID: ' . $screen->id );
        }
    } );
    bplugins_bfcm2026_init( array(
        'plugin_file'          => __FILE__,
        'pricing_url'          => 'https://bplugins.com/products/business-card-block/pricing/',
        'dashboard_screen_ids' => array('bcb_page_bcb-dashboard', 'edit-bcb'),
        'product_name'         => 'Business Card Block',
        'logo_url'             => 'https://ps.w.org/business-card-block/assets/icon.svg?rev=3623366',
        'title'                => 'Biggest Black Friday Sale is Live!',
        'description'          => 'The biggest deal of the year is here! Upgrade to Business Card Block Pro and unlock advanced features, WooCommerce integration, custom designs, scheduling, and powerful card experiences!',
        'badge_text'           => 'Flat 45% OFF',
        'features'             => array(
            '14 Business Card Layouts',
            'Premium Card Designs',
            'Advanced Customization',
            'Social Media Links',
            'Contact Information',
            'Responsive Layouts'
        ),
        'button_text'          => 'Upgrade to Pro Now',
        'action_text'          => 'Upgrade to Pro and get 45% off on this Black Friday',
        'primary_color'        => '#35A0A8',
        'secondary_color'      => '#101b55',
        'accent_color'         => '#ff6b35',
        'text_color'           => '#172033',
        'campaign_end'         => '2026-12-05 23:59:59',
    ) );
    require_once BCB_DIR_PATH . 'includes/utils/functions.php';
    require_once BCB_DIR_PATH . 'includes/plugin.php';
}
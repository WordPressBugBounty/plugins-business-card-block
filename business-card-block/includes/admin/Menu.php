<?php

if (!defined('ABSPATH')) {
    exit;
}



if (!class_exists('bcbAdminMenu')) {

    class bcbAdminMenu
    {



        public function __construct()
        {


            add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
            add_action('admin_menu', [$this, 'adminMenu']);
        }

        public function adminEnqueueScripts($hook)
        {
            if ('bcb_page_bcb-dashboard' === $hook) {

                wp_enqueue_style('bcb-admin-style', BCB_DIR_URL . 'build/admin-dashboard.css', false, BCB_PLUGIN_VERSION);
                wp_enqueue_script('bcb-admin-script', BCB_DIR_URL . 'build/admin-dashboard.js', ['react', 'react-dom', 'wp-data', "wp-api", "wp-util", "wp-i18n"], BCB_PLUGIN_VERSION, true);
            }
        }

        public function adminMenu()
        {


            add_submenu_page(
                "edit.php?post_type=bcb",
                __('Help & Demo - Business Card', 'business-card'),
                __('Help & Demo', 'business-card'),
                'manage_options',
                'bcb-dashboard',
                [$this, 'renderHelpAndDemoPage'],

            );
        }




        public function renderHelpAndDemoPage()
        {
?>
<div id='bcbHelpAndDemo' data-info='<?php echo esc_attr(wp_json_encode([
                                'version' => BCB_PLUGIN_VERSION,
                                'isPremium' => bcbIsPremium(),
                                'hasPro' => BCB_HAS_PRO
                            ])); ?>'></div>
<?php
        }
    }
    new bcbAdminMenu();
}
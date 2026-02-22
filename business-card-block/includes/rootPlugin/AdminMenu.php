<?php

namespace BCB;

class AdminMenu  {
    function __construct() {
        add_action('admin_menu', [$this, 'adminMenu']);
    }



    public function adminMenu(){

                add_submenu_page(
                    "edit.php?post_type=bcb",
                    __('Help & Demo - Business Card', 'business-card'),
                    __('Help & Demo', 'business-card'),
                    'manage_options',
                    'bcb-dashboard',
                    [$this, 'renderHelpAndDemoPage'],

                );
            }



            public function renderHelpAndDemoPage(){
            ?>
<div id='bcbHelpAndDemo' data-info='<?php echo esc_attr(wp_json_encode([
                    'version' => BCB_PLUGIN_VERSION,
                    'isPremium' => bcbIsPremium(),
                    'hasPro' => BCB_HAS_PRO,
                    'licenseActiveNonce' => wp_create_nonce( 'bpl_activation_nonce' )
                ])); ?>'></div>
<?php
        }
    }
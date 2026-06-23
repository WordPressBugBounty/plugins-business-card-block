<?php

namespace BCB;

class AdminMenu  {
    function __construct() {
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('wp_ajax_bcb_disabled_blocks', [$this, 'handleDisabledBlocks']);
    }

    /**
     * Persist the set of blocks the user has turned off from the dashboard
     * Blocks page. Stores an array of block names in the `bcbDisabledBlocks`
     * option, which Init.php reads to skip registering those blocks.
     */
    public function handleDisabledBlocks(){
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : null;
        if (!wp_verify_nonce($nonce, 'bcb_disabled_blocks') || !current_user_can('manage_options')) {
            wp_send_json_error('Invalid Request');
        }

        $data = isset($_POST['data']) ? json_decode(wp_unslash($_POST['data']), true) : null;

        if (is_array($data)) {
            $data = array_values(array_map('sanitize_text_field', $data));
            update_option('bcbDisabledBlocks', $data);
            wp_send_json_success($data);
        }

        wp_send_json_success(get_option('bcbDisabledBlocks', []));
    }

    public function adminMenu(){

                add_submenu_page(
                    "edit.php?post_type=bcb",
                    __('Help & Demos - Business Card', 'business-card'),
                    __('Help & Demos', 'business-card'),
                    'manage_options',
                    'bcb-dashboard',
                    [$this, 'renderHelpAndDemoPage'],

                );
            }


            public function renderHelpAndDemoPage(){
            ?>
<div id='bcbHelpAndDemos' data-info='<?php echo esc_attr(wp_json_encode([
                    'version' => BCB_PLUGIN_VERSION,
                    'isPremium' => bcbIsPremium(),
                    'hasPro' => BCB_HAS_PRO,
                    'licenseActiveNonce' => wp_create_nonce( 'bPlLicenseActivation' ),
                    'adminUrl' => admin_url(),
                    'disabledBlocksNonce' => wp_create_nonce( 'bcb_disabled_blocks' ),
                    'bcbDisabledBlocks' => get_option( 'bcbDisabledBlocks', [] )
                ])); ?>'></div>
<?php
        }
    }
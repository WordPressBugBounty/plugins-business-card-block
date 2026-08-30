<?php

if (!defined('ABSPATH')) exit;

if( !class_exists( 'BCBPlugin' ) ){
    class BCBPlugin{
        function __construct(){
            $this -> loaded_classes();

        }
 
        function loaded_classes(){
			// Shared card model. Loaded first: every block's render.php and the
			// shortcode, REST and Elementor layers all resolve through these.
			require_once BCB_DIR_PATH . 'includes/Card/Contacts.php';
			require_once BCB_DIR_PATH . 'includes/Card/Model.php';
			require_once BCB_DIR_PATH . 'includes/Card/Themes.php';
			require_once BCB_DIR_PATH . 'includes/Card/VCard.php';
			require_once BCB_DIR_PATH . 'includes/Card/QR.php';
			require_once BCB_DIR_PATH . 'includes/Card/Schema.php';
			require_once BCB_DIR_PATH . 'includes/Card/Render.php';

			require_once BCB_DIR_PATH . 'includes/rootPlugin/Init.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/Enqueue.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/AdminMenu.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/ShortCode.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/CustomColumn.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/Rest.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/Elementor.php';
			if( BCB_HAS_PRO ){
		require_once BCB_DIR_PATH . 'includes/rootPlugin/LicenseActivation.php';
	}

			new BCB\Init();
			new BCB\Enqueue();
			new BCB\AdminMenu();
			new BCB\ShortCode();
			new BCB\CustomColumn();
			new BCB\Rest();
			new BCB\Elementor();

		}
		
        
    }
    new BCBPlugin();
}
<?php

if (!defined('ABSPATH')) exit;

if( !class_exists( 'BCBPlugin' ) ){
    class BCBPlugin{
        function __construct(){
            $this -> loaded_classes();

        }
 
        function loaded_classes(){
			require_once BCB_DIR_PATH . 'includes/rootPlugin/Init.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/Enqueue.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/AdminMenu.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/ShortCode.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/CustomColumn.php';
			require_once BCB_DIR_PATH . 'includes/rootPlugin/BlockCategory.php';
			if( BCB_HAS_PRO ){
		require_once BCB_DIR_PATH . 'includes/rootPlugin/LicenseActivation.php';
	}


			new BCB\Init();
			new BCB\Enqueue();
			new BCB\AdminMenu();
			new BCB\ShortCode();
			new BCB\CustomColumn();
			new BCB\BlockCategory();
			

		}
		
        
    }
    new BCBPlugin();
}
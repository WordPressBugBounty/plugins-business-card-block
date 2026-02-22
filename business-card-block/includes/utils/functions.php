<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'bcbIsPremium' ) ) {
	function bcbIsPremium(){
		return BCB_HAS_PRO ? bcb_fs()->can_use_premium_code() : false;
	}
}

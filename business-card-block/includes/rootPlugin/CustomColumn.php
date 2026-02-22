<?php

namespace BCB;

class CustomColumn {
    function __construct() {
        add_filter('manage_bcb_posts_columns', [$this, 'manageBCBPostsColumns'], 10);
		add_action('manage_bcb_posts_custom_column', [$this, 'manageBCBPostsCustomColumns'], 10, 2);
	}

	function manageBCBPostsColumns($defaults){
		unset($defaults['date']);
		$defaults['shortcode'] = 'ShortCode';
		$defaults['date'] = 'Date';
		return $defaults;
	}

	function manageBCBPostsCustomColumns($column_name, $post_ID){
		if ($column_name == 'shortcode') {
			echo '<div class="bPlAdminShortcode" id="bPlAdminShortcode-' . esc_attr($post_ID) . '">
				<input value="[bcb id=' . esc_attr($post_ID) . ']" onclick="copyBPlAdminShortcode(\'' . esc_attr($post_ID) . '\')">
				<span class="tooltip">' . esc_html__('Copy To Clipboard', 'business-card') . '</span>
			</div>';
		}
	}


}
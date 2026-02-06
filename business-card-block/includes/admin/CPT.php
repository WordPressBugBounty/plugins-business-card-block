<?php

if (!defined('ABSPATH')) {
	exit;
}

class bcbCPT
{

	public function __construct()
	{
		add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
		add_action('init', [$this, 'onInit']);
		add_shortcode('bcb', [$this, 'onAddShortcode']);
		add_filter('manage_bcb_posts_columns', [$this, 'manageBCBPostsColumns'], 10);
		add_action('manage_bcb_posts_custom_column', [$this, 'manageBCBPostsCustomColumns'], 10, 2);
	}

	function adminEnqueueScripts($hook)
	{
if ( $hook === 'edit.php' ) {
			wp_enqueue_style('bcb-admin-post', BCB_DIR_URL . 'build/admin-post.css', [], BCB_PLUGIN_VERSION);

			wp_enqueue_script('bcb-admin-post', BCB_DIR_URL . 'build/admin-post.js', [], BCB_PLUGIN_VERSION, true);
		}
	}

	function onInit(){
		$menuIcon = "<svg fill='#ffffff' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'>
			<path d='M501.333,98.667H10.667C4.777,98.667,0,103.442,0,109.333v293.333c0,5.891,4.777,10.667,10.667,10.667h490.667 c5.89,0,10.667-4.775,10.667-10.667V109.333C512,103.442,507.224,98.667,501.333,98.667z M490.667,392H21.333V120h469.333V392z' />
			<path d='M47.476,260.802c1.749,0,3.522-0.431,5.159-1.338l25.123-13.918c11.038,19.286,31.806,32.32,55.574,32.32 c35.29,0,64-28.71,64-64c0-1.188-0.038-2.368-0.102-3.54l42.115-18.741c5.382-2.395,7.804-8.7,5.409-14.082 c-2.396-5.381-8.702-7.806-14.082-5.409l-38.313,17.049c-9.69-23.049-32.498-39.276-59.027-39.276c-35.29,0-64,28.71-64,64 c0,3.887,0.368,7.689,1.035,11.387l-28.07,15.55c-5.153,2.854-7.017,9.346-4.161,14.499 C40.083,258.818,43.723,260.802,47.476,260.802z M133.333,256.533c-15.753,0-29.529-8.588-36.919-21.32l24.103-13.353 l13.086,10.468c3.115,2.492,7.358,3.036,10.999,1.417l30.954-13.774C172.585,240.615,154.786,256.533,133.333,256.533z M133.333,171.2c17.852,0,33.17,11.026,39.524,26.622l-30.997,13.794l-13.597-10.878c-3.375-2.7-8.047-3.097-11.833-1.002 L90.67,214.008c0-0.048-0.003-0.094-0.003-0.141C90.667,190.34,109.807,171.2,133.333,171.2z' />
			<path d='M311.822,194.667h147.911c5.89,0,10.667-4.776,10.667-10.667s-4.777-10.667-10.667-10.667H311.822 c-5.89,0-10.667,4.776-10.667,10.667S305.932,194.667,311.822,194.667z' />
			<path d='M262.045,237.333H310.4c5.89,0,10.667-4.776,10.667-10.667S316.29,216,310.4,216h-48.355 c-5.89,0-10.667,4.776-10.667,10.667S256.155,237.333,262.045,237.333z' />
			<path d='M459.733,216H353.778c-5.89,0-10.667,4.776-10.667,10.667s4.777,10.667,10.667,10.667h105.955 c5.89,0,10.667-4.776,10.667-10.667S465.623,216,459.733,216z' />
			<path d='M459.733,258.667h-86.755c-5.89,0-10.667,4.775-10.667,10.667c0,5.891,4.777,10.667,10.667,10.667h86.755 c5.89,0,10.667-4.775,10.667-10.667C470.4,263.442,465.623,258.667,459.733,258.667z' />
			<path d='M212.978,322.667h60.8c5.89,0,10.667-4.775,10.667-10.667c0-5.891-4.777-10.667-10.667-10.667h-60.8 c-5.89,0-10.667,4.775-10.667,10.667C202.311,317.891,207.088,322.667,212.978,322.667z' />
			<path d='M411.733,312c0-5.891-4.776-10.667-10.667-10.667h-83.911c-5.89,0-10.667,4.775-10.667,10.667 c0,5.891,4.776,10.667,10.667,10.667h83.911C406.957,322.667,411.733,317.891,411.733,312z' />
			<path d='M459.733,301.333h-23.111c-5.89,0-10.667,4.775-10.667,10.667c0,5.891,4.777,10.667,10.667,10.667h23.111 c5.89,0,10.667-4.775,10.667-10.667C470.4,306.109,465.623,301.333,459.733,301.333z' />
			<path d='M459.733,344h-49.067c-5.89,0-10.667,4.775-10.667,10.667s4.777,10.667,10.667,10.667h49.067 c5.89,0,10.667-4.775,10.667-10.667S465.623,344,459.733,344z' />
			<path d='M365.867,344h-36.622c-5.89,0-10.667,4.775-10.667,10.667s4.777,10.667,10.667,10.667h36.622 c5.89,0,10.667-4.775,10.667-10.667S371.757,344,365.867,344z' />
			<path d='M287.289,344h-74.31c-5.89,0-10.667,4.775-10.667,10.667s4.777,10.667,10.667,10.667h74.31 c5.89,0,10.667-4.775,10.667-10.667S293.179,344,287.289,344z' />
		</svg>";

		register_post_type('bcb', [
			'labels' => [
				'name'          => __('Business Cards', 'business-card'),
				'singular_name' => __('Business Card', 'business-card'),
				'add_new'       => __('Add New', 'business-card'),
				'add_new_item'  => __('Add New Business Card', 'business-card'),
				'edit_item'     => __('Edit Business Card', 'business-card'),
				'new_item'      => __('New Business Card', 'business-card'),
				'view_item'     => __('View Business Card', 'business-card'),
				'search_items'  => __('Search Business Cards', 'business-card'),
				'not_found'     => __('Sorry, we couldn\'t find the Business Card you are looking for.', 'business-card'),
			],

			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'exclude_from_search' => true,
			'hierarchical'        => false,
			'has_archive'         => false,
			'capability_type'     => 'page',
			'menu_position'       => 22,
			'menu_icon'           => 'data:image/svg+xml;base64,' . base64_encode($menuIcon),
			'supports'            => ['title','editor'],
			'template'            => [["business/card"]],
			'template_lock'       => 'all',
			'rewrite'             => false,
		]);

	}

	function onAddShortcode($atts)
	{
		$post_id = $atts['id'];
		$post = get_post($post_id);

		if (!$post) {
			return '';
		}

		if (post_password_required($post)) {
			return get_the_password_form($post);
		}

		switch ($post->post_status) {
			case 'publish':
				return $this->displayContent($post);

			case 'private':
				if (current_user_can('read_private_posts')) {
					return $this->displayContent($post);
				}
				return '';

			case 'draft':
			case 'pending':
			case 'future':
				if (current_user_can('edit_post', $post_id)) {
					return $this->displayContent($post);
				}
				return '';

			default:
				return '';
		}
	}

	function displayContent($post)
	{
		$blocks = parse_blocks($post->post_content);
		return render_block($blocks[0]);
	}

	function manageBCBPostsColumns($defaults)
	{
		unset($defaults['date']);
		$defaults['shortcode'] = 'ShortCode';
		$defaults['date'] = 'Date';
		return $defaults;
	}

	function manageBCBPostsCustomColumns($column_name, $post_ID)
	{
		if ($column_name == 'shortcode') {
			echo '<div class="bPlAdminShortcode" id="bPlAdminShortcode-' . esc_attr($post_ID) . '">
				<input value="[bcb id=' . esc_attr($post_ID) . ']" onclick="copyBPlAdminShortcode(\'' . esc_attr($post_ID) . '\')">
				<span class="tooltip">' . esc_html__('Copy To Clipboard', 'business-card') . '</span>
			</div>';
		}
	}
}
new bcbCPT();
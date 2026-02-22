<?php
namespace BCB;

class Shortcode {
    function __construct() {
        add_shortcode('bcb', [$this, 'onAddShortcode']);
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

	function displayContent($post){
		$blocks = parse_blocks($post->post_content);
		return render_block($blocks[0]);
	}

    

    
}
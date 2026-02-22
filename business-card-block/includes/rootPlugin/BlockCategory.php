<?php
namespace BCB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BlockCategory {

    public function __construct() {
        add_filter( 'block_categories_all', [ $this, 'register_category' ], 10, 2 );
    }

    public function register_category( $categories, $post ) {

        array_unshift( $categories, [
            'slug'  => 'portfolio',
            'title' => __( 'Portfolio', 'portfolio-block' ),
        ]);

        return $categories;
    }
}

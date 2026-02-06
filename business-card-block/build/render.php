<?php
$id = wp_unique_id( 'bcbBusinessCard-' );

extract( $attributes );



$has_icon_class = false;

if ( ! empty( $contacts ) && is_array( $contacts ) ) {

    foreach ( $contacts as $contact ) {

        if ( isset( $contact['icon']['class'] ) && ! empty( $contact['icon']['class'] ) ) {
            $has_icon_class = true;
            break;
        }
    }
}

if ( $has_icon_class ) {
    wp_enqueue_style( 'fontAwesome' );
}


?>
<div <?php echo get_block_wrapper_attributes(); ?> id='<?php echo esc_attr( $id ); ?>'
    data-bcbIsPremium='<?php echo esc_attr(bcbIsPremium()); ?>'
    data-attributes='<?php echo esc_attr( wp_json_encode( $attributes ) ); ?>'></div>
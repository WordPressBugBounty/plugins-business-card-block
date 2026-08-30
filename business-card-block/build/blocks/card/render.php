<?php
/**
 * Server render for the Business Card block.
 *
 * The card itself is drawn client-side, so this emits the mount point plus the
 * two things only the server can produce: the JSON-LD block search engines
 * read, and the pre-computed contact links, vCard and QR image.
 *
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Render::card escapes internally.
echo \BCB\Card\Render::card( $attributes, array( 'prefix' => 'bcbBusinessCard-' ) );

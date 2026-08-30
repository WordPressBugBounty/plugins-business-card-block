<?php
/**
 * Shared server-side renderer for every Business Card block.
 *
 * All 15 blocks hydrate client-side from a JSON attribute blob, so this class
 * is the one place on the server where a card's data is turned into markup. It
 * produces three things:
 *
 * 1. A JSON-LD block, so the card is legible to crawlers that never run the
 *    React bundle.
 * 2. A payload script holding the resolved contact links, the vCard text and
 *    the QR SVG — computed once here rather than three times in JavaScript.
 * 3. The mount point the view script hydrates.
 *
 * @package BusinessCardBlock
 */

namespace BCB\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Render {

	/**
	 * Render a card block.
	 *
	 * @param array $attributes Raw block attributes.
	 * @param array $args       {
	 *     @type string $prefix     Prefix for the generated DOM id.
	 *     @type string $wrapper    Pre-built wrapper attributes. Defaults to
	 *                              get_block_wrapper_attributes().
	 * }
	 * @return string Block markup.
	 */
	public static function card( $attributes, $args = array() ) {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$prefix  = isset( $args['prefix'] ) ? $args['prefix'] : 'bcbBusinessCard-';
		$dom_id  = wp_unique_id( $prefix );
		$wrapper = isset( $args['wrapper'] ) ? $args['wrapper'] : get_block_wrapper_attributes();

		// Enforce the Pro gate before anything reaches the browser: a premium
		// design on an unlicensed site is swapped for the default layout here,
		// so the Pro theme name never appears in data-attributes at all.
		$requested = isset( $attributes['theme'] ) ? $attributes['theme'] : '';
		$allowed   = Themes::resolve( $requested );
		$downgraded = ( '' !== $requested && $allowed !== $requested );

		if ( $downgraded ) {
			$attributes['theme'] = $allowed;
		}

		$model = Model::from_attributes( $attributes );

		self::maybe_enqueue_font_awesome( $attributes );

		$payload = self::payload( $model, $downgraded );

		$out = Schema::render( $model );

		$out .= '<div ' . $wrapper // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped.
			. ' id="' . esc_attr( $dom_id ) . '"'
			. ' data-bcbIsPremium="' . esc_attr( bcbIsPremium() ) . '"'
			. ' data-attributes="' . esc_attr( wp_json_encode( $attributes ) ) . '">';

		// The payload rides inside the mount point as an inert JSON script.
		// The view script reads it before React takes over the container, which
		// avoids paying the attribute-encoding tax on a multi-kilobyte SVG.
		$out .= self::payload_script( $payload );

		$out .= '</div>';

		return $out;
	}

	/**
	 * Build everything the client needs that is cheaper to compute in PHP.
	 *
	 * @param array $model Normalised model.
	 * @param bool  $downgraded Whether a Pro theme was swapped for the default.
	 * @return array
	 */
	private static function payload( $model, $downgraded = false ) {
		$payload = array(
			'contacts' => self::contact_payload( $model ),
			'links'    => ! empty( $model['links']['contacts'] ),
		);

		// Only someone who can edit the post is told why the design changed;
		// visitors just see a working card.
		if ( $downgraded && current_user_can( 'edit_posts' ) ) {
			$payload['notice'] = __( 'This card was designed with a Pro theme. Showing the Default design until a Business Card Block Pro licence is active.', 'business-card-block' );
		}

		$vcard_enabled = ! empty( $model['features']['vcard']['enabled'] );
		$qr            = isset( $model['features']['qr'] ) ? $model['features']['qr'] : array();
		$qr_enabled    = ! empty( $qr['enabled'] );

		// The vCard text is needed by the download button and, when the QR
		// encodes contact data, by the encoder — so build it at most once.
		$vcard = '';
		if ( $vcard_enabled || ( $qr_enabled && 'vcard' === $qr['source'] ) ) {
			$vcard = VCard::build( $model );
		}

		if ( $vcard_enabled && '' !== $vcard ) {
			$payload['vcard'] = array(
				'text'     => $vcard,
				'filename' => VCard::filename( $model ),
			);
		}

		if ( $qr_enabled ) {
			$svg = self::qr_svg( $model, $qr, $vcard );
			if ( '' !== $svg ) {
				$payload['qr'] = array(
					'svg'     => $svg,
					'caption' => isset( $qr['caption'] ) ? $qr['caption'] : '',
				);
			}
		}

		return $payload;
	}

	/**
	 * Resolve the QR payload and encode it.
	 *
	 * @param array  $model Normalised model.
	 * @param array  $qr    QR settings.
	 * @param string $vcard Pre-built vCard text, if any.
	 * @return string SVG markup, or '' on failure.
	 */
	private static function qr_svg( $model, $qr, $vcard ) {
		$text = '';

		switch ( $qr['source'] ) {
			case 'url':
				$text = self::current_url();
				break;
			case 'custom':
				$text = $qr['custom'];
				break;
			case 'vcard':
			default:
				// A full vCard with a photo URL and social profiles pushes the
				// QR to a high version that phone cameras struggle with, so the
				// encoded copy drops the extras.
				$text = VCard::build( $model, array( 'compact' => true ) );
				if ( '' === $text ) {
					$text = $vcard;
				}
				break;
		}

		if ( '' === trim( $text ) ) {
			return '';
		}

		$name  = isset( $model['identity']['name'] ) ? $model['identity']['name'] : '';
		$title = '' !== $name
			/* translators: %s: the name on the business card. */
			? sprintf( __( 'QR code with the contact details for %s', 'business-card-block' ), $name )
			: __( 'QR code with this card\'s contact details', 'business-card-block' );

		return self::cached_qr(
			$text,
			array(
				'size'   => $qr['size'],
				'dark'   => $qr['dark'],
				'light'  => $qr['light'],
				'ecc'    => $qr['ecc'],
				'title'  => $title,
			)
		);
	}

	/**
	 * Per-request memo for QR encoding.
	 *
	 * @var array
	 */
	private static $qr_memo = array();

	/**
	 * Encode a QR code, reusing earlier results.
	 *
	 * Encoding is pure CPU — roughly 20ms for a vCard payload, dominated by
	 * scoring all eight mask patterns — and the output is fully determined by
	 * its inputs. Two layers of cache keep that off the page-render path: a
	 * static memo for repeated cards in one request, and a transient across
	 * requests.
	 *
	 * @param string $text Payload.
	 * @param array  $args QR::svg() arguments.
	 * @return string SVG markup, or '' on failure.
	 */
	private static function cached_qr( $text, $args ) {
		$key = md5( $text . '|' . wp_json_encode( $args ) );

		if ( isset( self::$qr_memo[ $key ] ) ) {
			return self::$qr_memo[ $key ];
		}

		$transient = 'bcb_qr_' . $key;
		$cached    = get_transient( $transient );

		if ( is_string( $cached ) ) {
			self::$qr_memo[ $key ] = $cached;
			return $cached;
		}

		$svg = QR::svg( $text, $args );

		if ( is_wp_error( $svg ) ) {
			$svg = '';
		}

		// Cache failures too, briefly, so an oversized payload is not re-encoded
		// on every request just to fail again.
		set_transient( $transient, $svg, '' === $svg ? HOUR_IN_SECONDS : WEEK_IN_SECONDS );

		self::$qr_memo[ $key ] = $svg;

		return $svg;
	}

	/**
	 * Flatten resolved contacts for the client.
	 *
	 * Only what a renderer needs — the editor gets the scheme table instead, so
	 * neither side re-implements the URL formats.
	 *
	 * @param array $model Normalised model.
	 * @return array[]
	 */
	private static function contact_payload( $model ) {
		$out = array();

		if ( empty( $model['contacts'] ) || ! is_array( $model['contacts'] ) ) {
			return $out;
		}

		foreach ( $model['contacts'] as $contact ) {
			$out[] = array(
				'href'     => isset( $contact['href'] ) ? $contact['href'] : '',
				'copyable' => ! empty( $contact['copyable'] ),
				'label'    => isset( $contact['label'] ) ? $contact['label'] : '',
			);
		}

		return $out;
	}

	/**
	 * Wrap the payload in an inert JSON script element.
	 *
	 * @param array $payload Payload data.
	 * @return string
	 */
	private static function payload_script( $payload ) {
		$json = wp_json_encode( $payload, Schema::JSON_FLAGS );

		if ( false === $json ) {
			return '';
		}

		return '<script type="application/json" class="bcb-card-payload">' . $json . '</script>';
	}

	/**
	 * The canonical URL of the page the card is rendering on.
	 *
	 * @return string
	 */
	private static function current_url() {
		$post_id = get_the_ID();

		if ( $post_id ) {
			$permalink = get_permalink( $post_id );
			if ( $permalink ) {
				return $permalink;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Load the icon font only for cards that use icon classes.
	 *
	 * Preserves the existing per-block behaviour: cards drawn with inline SVG
	 * never pull the 100KB stylesheet.
	 *
	 * @param array $attributes Raw block attributes.
	 * @return void
	 */
	private static function maybe_enqueue_font_awesome( $attributes ) {
		if ( empty( $attributes['contacts'] ) || ! is_array( $attributes['contacts'] ) ) {
			return;
		}

		foreach ( $attributes['contacts'] as $contact ) {
			if ( ! empty( $contact['icon']['class'] ) ) {
				wp_enqueue_style( 'fontAwesome' );
				return;
			}
		}
	}
}

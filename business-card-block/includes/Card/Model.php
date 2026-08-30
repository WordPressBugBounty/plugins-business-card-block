<?php
/**
 * Normalised Business Card data model.
 *
 * Every consumer — Gutenberg's render.php, the shortcode, the Elementor
 * widget, the vCard builder, the QR encoder and the Schema.org builder —
 * reads the card through this class, so none of them has to know which block
 * produced the attributes.
 *
 * Two attribute shapes exist in the wild and both are supported:
 *
 * - The core `business/card` block keeps company/tagline inside the
 *   `businessCard` object.
 * - The 14 industry blocks promote `company` and `tagline` to top level.
 *
 * @package BusinessCardBlock
 */

namespace BCB\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Model {

	/**
	 * Build the normalised model from raw block attributes.
	 *
	 * @param array $attributes Raw block attributes.
	 * @return array {
	 *     @type array $identity  name, title, organization, tagline, avatar, logo.
	 *     @type array $contacts  Resolved contact rows (see Contacts::resolve).
	 *     @type array $display   theme, alignment.
	 *     @type array $features  qr, vcard, schema settings.
	 *     @type array $socials   Sanitised social links.
	 * }
	 */
	public static function from_attributes( $attributes ) {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$business_card = isset( $attributes['businessCard'] ) && is_array( $attributes['businessCard'] )
			? $attributes['businessCard']
			: array();

		$contacts = isset( $attributes['contacts'] ) && is_array( $attributes['contacts'] )
			? $attributes['contacts']
			: array();

		return array(
			'identity' => array(
				'name'         => self::text( self::pick( $attributes, 'name' ) ),
				'title'        => self::text( self::pick( $attributes, 'title' ) ),
				// Industry blocks put these at top level, the core block nests
				// them; prefer whichever is actually filled in.
				'organization' => self::text( self::pick( $attributes, 'company', self::pick( $business_card, 'company' ) ) ),
				'tagline'      => self::text( self::pick( $attributes, 'tagline', self::pick( $business_card, 'tagline' ) ) ),
				'avatar'       => self::image( $attributes, 'avatar', 'showAvatar' ),
				'logo'         => self::image( $attributes, 'logo', 'showLogo' ),
			),
			'contacts' => Contacts::resolve_all( $contacts ),
			'socials'  => self::socials( $attributes ),
			'display'  => array(
				'theme'     => self::slug( self::pick( $attributes, 'theme', 'default' ) ),
				'alignment' => self::slug( self::pick( $attributes, 'alignment', 'center' ) ),
			),
			'features' => array(
				'qr'     => self::qr_settings( $attributes ),
				'vcard'  => array(
					'enabled' => (bool) self::pick( $business_card, 'isDownloadBtn', false ),
					'label'   => self::text( self::pick( $business_card, 'downloadLabel', __( 'Save Contact', 'business-card-block' ) ) ),
				),
				'schema' => array(
					// Structured data is opt-out: existing cards gain it on
					// upgrade, which is the point of the feature.
					'enabled' => self::bool( self::pick( $attributes, 'schemaEnabled', true ) ),
					'type'    => self::slug( self::pick( $attributes, 'schemaType', 'auto' ) ),
				),
			),
			'links'    => array(
				// Whether contact rows render as anchors. Defaults on; the CSS
				// keeps them looking exactly like the old plain-text rows.
				'contacts' => self::bool( self::pick( $attributes, 'contactLinks', true ) ),
			),
		);
	}

	/**
	 * Normalise the QR settings block.
	 *
	 * @param array $attributes Raw block attributes.
	 * @return array
	 */
	private static function qr_settings( $attributes ) {
		$qr = isset( $attributes['qr'] ) && is_array( $attributes['qr'] ) ? $attributes['qr'] : array();

		$source = self::slug( self::pick( $qr, 'source', 'vcard' ) );
		if ( ! in_array( $source, array( 'vcard', 'url', 'custom' ), true ) ) {
			$source = 'vcard';
		}

		$size = (int) self::pick( $qr, 'size', 140 );
		$size = max( 64, min( 512, $size ) );

		$badge = self::slug( self::pick( $qr, 'badge', 'none' ) );
		if ( ! in_array( $badge, array( 'none', 'initials', 'logo' ), true ) ) {
			$badge = 'none';
		}

		$ecc = self::ecc( self::pick( $qr, 'ecc', 'M' ) );

		// Presentation. "standard" is the in-flow panel every card has always
		// used, so an attribute set saved before this feature existed normalises
		// to exactly the old behaviour.
		$display = self::slug( self::pick( $qr, 'display', 'standard' ) );
		if ( ! in_array( $display, array( 'standard', 'flip', 'reveal' ), true ) ) {
			$display = 'standard';
		}

		$corners = array( 'top-right', 'top-left', 'bottom-right', 'bottom-left' );
		$trigger = self::slug( self::pick( $qr, 'triggerPosition', 'top-right' ) );
		if ( ! in_array( $trigger, $corners, true ) ) {
			$trigger = 'top-right';
		}

		$positions     = array(
			'top-left',
			'top-center',
			'top-right',
			'center-left',
			'center',
			'center-right',
			'bottom-left',
			'bottom-center',
			'bottom-right',
			'custom',
		);
		$back_position = self::slug( self::pick( $qr, 'backPosition', 'center' ) );
		if ( ! in_array( $back_position, $positions, true ) ) {
			$back_position = 'center';
		}

		// A centre badge is an obstruction. Level M tolerates roughly 15% damage
		// and the badge covers close to a tenth of the symbol, which leaves no
		// margin for a scuffed screen or a cheap camera — so raise the level
		// rather than ship a code that scans on the desk and fails in the field.
		if ( 'none' !== $badge && in_array( $ecc, array( 'L', 'M' ), true ) ) {
			$ecc = 'Q';
		}

		return array(
			'enabled'   => self::bool( self::pick( $qr, 'enable', false ) ),
			'source'    => $source,
			'custom'    => Contacts::safe_url( self::text( self::pick( $qr, 'custom' ) ) ),
			'size'      => $size,
			'dark'      => self::color( self::pick( $qr, 'dark', '#000000' ), '#000000' ),
			'light'     => self::color( self::pick( $qr, 'light', '#ffffff' ), '#ffffff' ),
			'ecc'       => $ecc,
			'badge'     => $badge,
			'label'     => self::text( self::pick( $qr, 'label', __( 'Scan to connect', 'business-card-block' ) ) ),
			'caption'   => self::text( self::pick( $qr, 'caption' ) ),
			'display'      => $display,
			'backPosition' => $back_position,
			'backX'        => self::clamp_pct( self::pick( $qr, 'backX', 50 ) ),
			'backY'        => self::clamp_pct( self::pick( $qr, 'backY', 50 ) ),
			'backHint'        => self::text( self::pick( $qr, 'backHint' ) ),
			'triggerPosition' => $trigger,
		);
	}

	/**
	 * Clamp a percentage to 0-100.
	 *
	 * Back-face coordinates arrive straight from a range control and can be
	 * posted by anything, so they are bounded here as well as in the CSS.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private static function clamp_pct( $value ) {
		$n = is_numeric( $value ) ? (int) round( (float) $value ) : 50;

		return max( 0, min( 100, $n ) );
	}

	/**
	 * Sanitise the socials array down to safe links.
	 *
	 * @param array $attributes Raw block attributes.
	 * @return array[]
	 */
	private static function socials( $attributes ) {
		if ( empty( $attributes['socials'] ) || ! is_array( $attributes['socials'] ) ) {
			return array();
		}

		$out = array();
		foreach ( $attributes['socials'] as $social ) {
			if ( ! is_array( $social ) || empty( $social['link'] ) ) {
				continue;
			}

			$link = Contacts::safe_url( trim( (string) $social['link'] ) );
			if ( '' === $link ) {
				continue;
			}

			// The stock defaults ship example.com placeholders; they would
			// otherwise leak into sameAs and hurt the structured data.
			if ( false !== stripos( $link, '//example.com' ) ) {
				continue;
			}

			$out[] = array(
				'link'         => $link,
				'openInNewTab' => ! empty( $social['openInNewTab'] ),
			);
		}

		return $out;
	}

	/**
	 * Normalise an image attribute plus its visibility toggle.
	 *
	 * @param array  $attributes Raw block attributes.
	 * @param string $key        Attribute key ( avatar | logo ).
	 * @param string $toggle     Visibility attribute key.
	 * @return array
	 */
	private static function image( $attributes, $key, $toggle ) {
		$image = isset( $attributes[ $key ] ) && is_array( $attributes[ $key ] ) ? $attributes[ $key ] : array();
		$url   = isset( $image['url'] ) ? trim( (string) $image['url'] ) : '';

		return array(
			'url'     => '' === $url ? '' : esc_url_raw( $url, array( 'http', 'https' ) ),
			'id'      => isset( $image['id'] ) ? absint( $image['id'] ) : 0,
			'visible' => ! empty( $attributes[ $toggle ] ),
		);
	}

	/**
	 * Read a key with a fallback, treating empty strings as absent.
	 *
	 * @param array  $source   Array to read from.
	 * @param string $key      Key to read.
	 * @param mixed  $fallback Value when the key is missing or blank.
	 * @return mixed
	 */
	private static function pick( $source, $key, $fallback = '' ) {
		if ( ! is_array( $source ) || ! isset( $source[ $key ] ) ) {
			return $fallback;
		}

		if ( is_string( $source[ $key ] ) && '' === trim( $source[ $key ] ) ) {
			return $fallback;
		}

		return $source[ $key ];
	}

	/**
	 * Flatten a user string to safe plain text.
	 *
	 * Card fields accept a little inline markup in the editor; none of it is
	 * meaningful in a vCard, a QR payload or JSON-LD, so it is stripped here
	 * rather than at each call site.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function text( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = wp_strip_all_tags( (string) $value, true );
		$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );

		return trim( sanitize_text_field( $value ) );
	}

	/**
	 * Normalise a slug-ish attribute value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function slug( $value ) {
		return is_scalar( $value ) ? preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ) : '';
	}

	/**
	 * Coerce loosely-typed attribute values to bool.
	 *
	 * Attributes arriving from shortcode text are strings, so "false" and "0"
	 * have to be understood as false.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function bool( $value ) {
		if ( is_string( $value ) ) {
			return ! in_array( strtolower( trim( $value ) ), array( '', '0', 'false', 'no', 'off' ), true );
		}

		return (bool) $value;
	}

	/**
	 * Validate a CSS colour, falling back when it is not a plain hex value.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Safe default.
	 * @return string
	 */
	private static function color( $value, $fallback ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( 'transparent' === strtolower( $value ) ) {
				return 'transparent';
			}
			if ( preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
				return $value;
			}
		}

		return $fallback;
	}


	/**
	 * Clamp the error-correction level to a supported value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function ecc( $value ) {
		$value = is_scalar( $value ) ? strtoupper( trim( (string) $value ) ) : '';

		return in_array( $value, array( 'L', 'M', 'Q', 'H' ), true ) ? $value : 'M';
	}
}

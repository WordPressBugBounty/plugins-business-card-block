<?php
/**
 * Contact-method registry and link resolver.
 *
 * Single source of truth for how each contact type turns into a clickable
 * target. Gutenberg, the frontend view script, the shortcode, the Elementor
 * widget, the vCard builder and the Schema.org builder all resolve through
 * here so a URL format is never written down twice.
 *
 * @package BusinessCardBlock
 */

namespace BCB\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Contacts {

	/**
	 * Cached type registry.
	 *
	 * @var array|null
	 */
	private static $types = null;

	/**
	 * The contact-method registry.
	 *
	 * Each entry describes one contact type end to end:
	 *
	 * - label     Human readable name (translated).
	 * - scheme    Link template, or '' when the type has no linkable target.
	 *             Tokens: {raw} {tel} {digits} {handle} {enc} {url}
	 * - passthru  Host fragments that mean "the user already pasted a full URL";
	 *             when the value contains one it is used verbatim instead of the
	 *             template.
	 * - copyable  True when there is no usable deep link and the best UX is to
	 *             let the visitor copy the identifier.
	 * - vcard     vCard property spec, or null when the type is not exported.
	 * - schema    Schema.org property name, or null.
	 *
	 * @return array
	 */
	public static function types() {
		if ( null !== self::$types ) {
			return self::$types;
		}

		self::$types = array(
			'address'  => array(
				'label'    => __( 'Address', 'business-card-block' ),
				'scheme'   => 'https://www.google.com/maps/search/?api=1&query={enc}',
				'passthru' => array( 'google.com/maps', 'maps.app.goo.gl', 'openstreetmap.org' ),
				'copyable' => false,
				'vcard'    => array( 'prop' => 'ADR', 'params' => 'TYPE=WORK', 'structured' => 'adr' ),
				'schema'   => 'address',
			),
			'phone'    => array(
				'label'    => __( 'Phone', 'business-card-block' ),
				'scheme'   => 'tel:{tel}',
				'passthru' => array(),
				'copyable' => false,
				'vcard'    => array( 'prop' => 'TEL', 'params' => 'TYPE=CELL' ),
				'schema'   => 'telephone',
			),
			'email'    => array(
				'label'    => __( 'Email', 'business-card-block' ),
				'scheme'   => 'mailto:{raw}',
				'passthru' => array(),
				'copyable' => false,
				'vcard'    => array( 'prop' => 'EMAIL', 'params' => 'TYPE=INTERNET' ),
				'schema'   => 'email',
			),
			'website'  => array(
				'label'    => __( 'Website', 'business-card-block' ),
				'scheme'   => '{url}',
				'passthru' => array(),
				'copyable' => false,
				'vcard'    => array( 'prop' => 'URL', 'params' => '' ),
				'schema'   => 'url',
			),
			'whatsapp' => array(
				'label'    => __( 'WhatsApp', 'business-card-block' ),
				'scheme'   => 'https://wa.me/{digits}',
				'passthru' => array( 'wa.me', 'api.whatsapp.com', 'chat.whatsapp.com' ),
				'copyable' => false,
				'vcard'    => array( 'prop' => 'X-SOCIALPROFILE', 'params' => 'TYPE=whatsapp', 'value' => 'href' ),
				'schema'   => null,
			),
			'telegram' => array(
				'label'    => __( 'Telegram', 'business-card-block' ),
				'scheme'   => 'https://t.me/{handle}',
				'passthru' => array( 't.me', 'telegram.me', 'telegram.dog' ),
				'copyable' => false,
				'vcard'    => array( 'prop' => 'X-SOCIALPROFILE', 'params' => 'TYPE=telegram', 'value' => 'href' ),
				'schema'   => null,
			),
			'skype'    => array(
				'label'    => __( 'Skype', 'business-card-block' ),
				'scheme'   => 'skype:{handle}?chat',
				'passthru' => array( 'join.skype.com' ),
				'copyable' => false,
				'vcard'    => array( 'prop' => 'IMPP', 'params' => 'X-SERVICE-TYPE=Skype', 'value' => 'href' ),
				'schema'   => null,
			),
			// IMO and WeChat publish no documented, universally supported web or
			// deep-link entry point. Rather than emit a link that silently fails
			// (or worse, an unvalidated custom scheme), the card shows the
			// identifier with a copy-to-clipboard action.
			'imo'      => array(
				'label'    => __( 'IMO', 'business-card-block' ),
				'scheme'   => '',
				'passthru' => array(),
				'copyable' => true,
				'vcard'    => array( 'prop' => 'X-SOCIALPROFILE', 'params' => 'TYPE=imo', 'value' => 'raw' ),
				'schema'   => null,
			),
			'wechat'   => array(
				'label'    => __( 'WeChat', 'business-card-block' ),
				'scheme'   => '',
				'passthru' => array(),
				'copyable' => true,
				'vcard'    => array( 'prop' => 'X-SOCIALPROFILE', 'params' => 'TYPE=wechat', 'value' => 'raw' ),
				'schema'   => null,
			),
			'others'   => array(
				'label'    => __( 'Others', 'business-card-block' ),
				'scheme'   => '{url}',
				'passthru' => array(),
				'copyable' => false,
				'vcard'    => null,
				'schema'   => null,
			),
		);

		return self::$types;
	}

	/**
	 * Default inline-SVG glyph for each contact type.
	 *
	 * Used when a contact row has no icon of its own — which happens for rows
	 * built by the Elementor widget, and for the new messaging types the icon
	 * library has no opinion about. Keeping them here means the editor, the
	 * widget and the shortcode all show the same glyph for the same type.
	 *
	 * @param string $type Type slug.
	 * @return string Inline SVG markup, or '' for an unknown type.
	 */
	public static function icon( $type ) {
		$paths = array(
			'address'  => array( '0 0 384 512', 'M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z' ),
			'phone'    => array( '0 0 512 512', 'M347.1 24.6c7.7-18.6 28-28.5 47.4-23.2l88 24C499.9 30.2 512 46 512 64c0 247.4-200.6 448-448 448c-18 0-33.8-12.1-38.6-29.5l-24-88c-5.3-19.4 4.6-39.7 23.2-47.4l96-40c16.3-6.8 35.2-2.1 46.3 11.6L207.3 368c70.4-33.3 127.4-90.3 160.7-160.7L318.7 167c-13.7-11.2-18.4-30-11.6-46.3l40-96z' ),
			'email'    => array( '0 0 512 512', 'M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4L236.8 313.6c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z' ),
			'website'  => array( '0 0 512 512', 'M352 256c0 22.2-1.2 43.6-3.3 64H163.3c-2.2-20.4-3.3-41.8-3.3-64s1.2-43.6 3.3-64H348.7c2.2 20.4 3.3 41.8 3.3 64zm28.8-64H503.9c5.3 20.5 8.1 41.9 8.1 64s-2.8 43.5-8.1 64H380.8c2.1-20.6 3.2-42 3.2-64s-1.1-43.4-3.2-64zm112.6-32H376.7c-10-63.9-29.8-117.4-55.3-151.6c78.3 20.7 142 77.5 171.9 151.6zm-149.1 0H167.7c6.1-36.4 15.5-68.6 27-94.7c10.5-23.6 22.2-40.7 33.5-51.5C239.4 3.2 248.7 0 256 0s16.6 3.2 27.8 13.8c11.3 10.8 23 27.9 33.5 51.5c11.6 26 20.9 58.2 27 94.7zm-209 0H18.6C48.6 85.9 112.2 29.1 190.6 8.4C165.1 42.6 145.3 96.1 135.3 160zM8.1 192H131.2c-2.1 20.6-3.2 42-3.2 64s1.1 43.4 3.2 64H8.1C2.8 299.5 0 278.1 0 256s2.8-43.5 8.1-64zM194.7 446.6c-11.6-26-20.9-58.2-27-94.6H344.3c-6.1 36.4-15.5 68.6-27 94.6c-10.5 23.6-22.2 40.7-33.5 51.5C272.6 508.8 263.3 512 256 512s-16.6-3.2-27.8-13.8c-11.3-10.8-23-27.9-33.5-51.5zM135.3 352c10 63.9 29.8 117.4 55.3 151.6C112.2 482.9 48.6 426.1 18.6 352H135.3zm358.1 0c-30 74.1-93.6 130.9-171.9 151.6c25.5-34.2 45.2-87.7 55.3-151.6H493.4z' ),
			'whatsapp' => array( '0 0 448 512', 'M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z' ),
			'telegram' => array( '0 0 496 512', 'M248 8C111 8 0 119 0 256s111 248 248 248 248-111 248-248S385 8 248 8zm121.8 169.9l-40.7 191.8c-3 13.6-11.1 16.9-22.4 10.5l-62-45.7-29.9 28.8c-3.3 3.3-6.1 6.1-12.5 6.1l4.4-63.1 114.9-103.8c5-4.4-1.1-6.9-7.7-2.5l-142 89.4-61.2-19.1c-13.3-4.2-13.6-13.3 2.8-19.7l239.1-92.2c11.1-4 20.8 2.7 17.2 19.5z' ),
			'skype'    => array( '0 0 448 512', 'M424.7 299.8c2.9-14 4.7-28.9 4.7-43.8 0-113.5-91.9-205.3-205.3-205.3-14.9 0-29.7 1.7-43.8 4.7C161.3 40.7 137.7 32 112 32 50.2 32 0 82.2 0 144c0 25.7 8.7 49.3 23.3 68.2-2.9 14-4.7 28.9-4.7 43.8 0 113.5 91.9 205.3 205.3 205.3 14.9 0 29.7-1.7 43.8-4.7 19 14.6 42.6 23.3 68.2 23.3 61.8 0 112-50.2 112-112 .1-25.6-8.6-49.2-23.2-68.1zm-194.6 91.5c-65.6 0-120.5-29.2-120.5-65 0-16 9.6-30.6 27.6-30.6 31.5 0 23.1 45.2 92.9 45.2 35.8 0 55.6-19.4 55.6-39.3 0-23.9-20.8-27.7-54.6-36.1-72.2-18.1-125.3-25.9-125.3-87.6 0-56.9 55.2-79.8 106.5-79.8 55.1 0 110.9 20.1 110.9 55.9 0 17.1-11.4 31.5-30.1 31.5-27.8 0-28.3-38.9-84.9-38.9-31.9 0-51.7 12.8-51.7 33.2 0 25.2 26.1 27.7 79.8 40.3 46.3 10.8 100.3 26.3 100.3 82.6 0 57.1-53.1 88.6-106.5 88.6z' ),
			'wechat'   => array( '0 0 576 512', 'M385.2 167.6c6.4 0 12.6.3 18.8 1.1C387.4 90.3 303.3 32 207.7 32 100.5 32 13 104.8 13 197.4c0 53.4 29.3 97.5 77.9 131.6l-19.3 58.6 68-34.1c24.4 4.8 43.8 9.7 68.2 9.7 6.2 0 12.1-.3 18.3-.8-4-12.9-6.2-26.6-6.2-40.8-.1-84.9 72.9-154 165.3-154zm-104.5-52.9c14.5 0 24.2 9.7 24.2 24.4 0 14.5-9.7 24.2-24.2 24.2-14.8 0-29.3-9.7-29.3-24.2.1-14.7 14.6-24.4 29.3-24.4zm-136.4 48.6c-14.5 0-29.3-9.7-29.3-24.2 0-14.8 14.8-24.4 29.3-24.4 14.8 0 24.4 9.7 24.4 24.4 0 14.6-9.6 24.2-24.4 24.2zM563 319.4c0-77.9-77.9-141.3-165.4-141.3-92.7 0-165.4 63.4-165.4 141.3S305 460.7 397.6 460.7c19.3 0 38.9-4.8 58.6-9.7l53.4 29.3-14.8-48.6C534 402 563 363.2 563 319.4zm-219.1-24.5c-9.7 0-19.3-9.7-19.3-19.6 0-9.7 9.7-19.3 19.3-19.3 14.8 0 24.4 9.7 24.4 19.3 0 10-9.7 19.6-24.4 19.6zm107.1 0c-9.7 0-19.3-9.7-19.3-19.6 0-9.7 9.7-19.3 19.3-19.3 14.5 0 24.4 9.7 24.4 19.3.1 10-9.9 19.6-24.4 19.6z' ),
			// IMO ships no brand glyph in the icon set, so a neutral chat mark
			// stands in rather than an unrelated logo.
			'imo'      => array( '0 0 512 512', 'M256 448c141.4 0 256-93.1 256-208S397.4 32 256 32S0 125.1 0 240c0 45.1 17.7 86.8 47.7 120.9c-1.9 24.5-11.4 46.3-21.4 62.9c-5.5 9.2-11.1 16.6-15.2 21.6c-4.6 4.6-5.9 11.4-3.4 17.4c2.5 6 8.3 9.9 14.8 9.9c28.7 0 57.6-8.9 81.6-19.3c22.9-10 42.4-21.9 54.3-30.6c31.8 11.5 67 17.9 104.1 17.9zM128 208a32 32 0 1 1 0 64 32 32 0 1 1 0-64zm96 32a32 32 0 1 1 64 0 32 32 0 1 1 -64 0zm160-32a32 32 0 1 1 0 64 32 32 0 1 1 0-64z' ),
			'others'   => array( '0 0 512 512', 'M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z' ),
		);

		if ( ! isset( $paths[ $type ] ) ) {
			return '';
		}

		return "<svg xmlns='http://www.w3.org/2000/svg' viewBox='" . $paths[ $type ][0] . "'><path d='" . $paths[ $type ][1] . "'/></svg>";
	}

	/**
	 * Protocols the card is allowed to emit.
	 *
	 * WordPress' default allow-list covers http/https/mailto/tel but not skype,
	 * so esc_url() would strip the Skype action without this.
	 *
	 * @return string[]
	 */
	public static function allowed_protocols() {
		return array( 'http', 'https', 'mailto', 'tel', 'skype' );
	}

	/**
	 * Resolve one contact entry into everything a renderer needs.
	 *
	 * @param array $contact Raw contact attribute ( type, text, icon ).
	 * @return array {
	 *     @type string $type     Normalised type slug.
	 *     @type string $text     Display text.
	 *     @type string $href     Safe URL, or '' when not linkable.
	 *     @type bool   $copyable Whether to offer copy-to-clipboard.
	 *     @type string $label    Accessible label for the action.
	 * }
	 */
	public static function resolve( $contact ) {
		$types = self::types();

		$type = isset( $contact['type'] ) ? strtolower( trim( (string) $contact['type'] ) ) : '';
		$text = isset( $contact['text'] ) ? trim( (string) $contact['text'] ) : '';

		$resolved = array(
			'type'     => $type,
			'text'     => $text,
			'href'     => '',
			'copyable' => false,
			'label'    => '',
		);

		if ( '' === $text || ! isset( $types[ $type ] ) ) {
			return $resolved;
		}

		$def                  = $types[ $type ];
		$resolved['copyable'] = ! empty( $def['copyable'] );
		/* translators: 1: contact method name, 2: the contact value. */
		$resolved['label'] = sprintf( __( '%1$s: %2$s', 'business-card-block' ), $def['label'], $text );

		if ( '' === $def['scheme'] ) {
			return $resolved;
		}

		$resolved['href'] = self::build_href( $def, $text );

		return $resolved;
	}

	/**
	 * Resolve a whole contacts array, preserving order and index.
	 *
	 * @param array $contacts Raw contacts attribute.
	 * @return array[]
	 */
	public static function resolve_all( $contacts ) {
		if ( ! is_array( $contacts ) ) {
			return array();
		}

		$out = array();
		foreach ( $contacts as $contact ) {
			if ( is_array( $contact ) ) {
				$out[] = self::resolve( $contact );
			}
		}

		return $out;
	}

	/**
	 * Expand a scheme template into a validated URL.
	 *
	 * @param array  $def  Type definition.
	 * @param string $text Raw user value.
	 * @return string Safe URL, or '' when nothing valid could be built.
	 */
	private static function build_href( $def, $text ) {
		// The user pasted a full URL for a type that has a canonical host —
		// honour it rather than mangling it through the template.
		if ( ! empty( $def['passthru'] ) && preg_match( '#^https?://#i', $text ) ) {
			foreach ( $def['passthru'] as $host ) {
				if ( false !== stripos( $text, $host ) ) {
					return self::safe_url( $text );
				}
			}
		}

		$tokens = array(
			'{raw}'     => $text,
			'{tel}'     => self::tel( $text ),
			'{digits}'  => self::digits( $text ),
			'{handle}'  => self::handle( $text ),
			'{enc}'     => rawurlencode( $text ),
			'{url}'     => self::normalize_url( $text ),
		);

		// A template whose token collapsed to nothing must not become a bare
		// "tel:" or "https://t.me/" link, so bail before substituting.
		foreach ( $tokens as $token => $value ) {
			if ( false !== strpos( $def['scheme'], $token ) && '' === $value ) {
				return '';
			}
		}

		// An address that is not a real email should not become a mailto: link.
		if ( false !== strpos( $def['scheme'], 'mailto:' ) && ! is_email( $text ) ) {
			return '';
		}

		$url = str_replace( array_keys( $tokens ), array_values( $tokens ), $def['scheme'] );

		return self::safe_url( $url );
	}

	/**
	 * Run a URL through esc_url with the card's protocol allow-list.
	 *
	 * @param string $url URL to sanitise.
	 * @return string
	 */
	public static function safe_url( $url ) {
		if ( '' === $url ) {
			return '';
		}

		return esc_url_raw( $url, self::allowed_protocols() );
	}

	/**
	 * Digits only, preserving a leading international "+".
	 *
	 * @param string $text Raw value.
	 * @return string
	 */
	private static function tel( $text ) {
		$plus   = ( 0 === strpos( ltrim( $text ), '+' ) ) ? '+' : '';
		$digits = self::digits( $text );

		return '' === $digits ? '' : $plus . $digits;
	}

	/**
	 * Strip everything that is not a digit.
	 *
	 * @param string $text Raw value.
	 * @return string
	 */
	private static function digits( $text ) {
		return preg_replace( '/\D+/', '', $text );
	}

	/**
	 * Reduce a value to a bare account handle.
	 *
	 * Accepts "@name", "name", or a full profile URL and returns "name".
	 *
	 * @param string $text Raw value.
	 * @return string
	 */
	private static function handle( $text ) {
		$handle = trim( $text );
		$handle = preg_replace( '#^[a-z][a-z0-9+.-]*://#i', '', $handle );
		$handle = preg_replace( '#^[^/]*/#', '', $handle, 1, $replaced );

		// Only the URL form has a slash to strip; a bare handle keeps its value.
		if ( ! $replaced ) {
			$handle = trim( $text );
		}

		$handle = ltrim( $handle, '@/' );
		// Delimiter is "~" on purpose: "#" appears inside the character class.
		$handle = preg_replace( '~[/?#].*$~', '', $handle );

		// Handles are conservative on purpose: anything outside this set would
		// have to be percent-encoded to be safe in a URL path.
		$handle = preg_replace( '/[^A-Za-z0-9._:-]/', '', $handle );
		$handle = trim( $handle, '.' );

		// A "handle" of nothing but punctuation is leftover path noise (".."),
		// not an account name — refuse it rather than build a dead link.
		return preg_match( '/[A-Za-z0-9]/', $handle ) ? $handle : '';
	}

	/**
	 * Coerce a user-entered website into an absolute http(s) URL.
	 *
	 * @param string $text Raw value.
	 * @return string
	 */
	private static function normalize_url( $text ) {
		$url = trim( $text );

		if ( '' === $url ) {
			return '';
		}

		// Reject any scheme other than http/https outright — this is the guard
		// that keeps javascript: and data: out of the card.
		if ( preg_match( '#^([a-z][a-z0-9+.-]*):#i', $url, $m ) ) {
			$scheme = strtolower( $m[1] );
			if ( 'http' !== $scheme && 'https' !== $scheme ) {
				return '';
			}
			return $url;
		}

		// Protocol-relative and bare domains both become https.
		$url = ltrim( $url, '/' );

		return 'https://' . $url;
	}

	/**
	 * The scheme table handed to the editor.
	 *
	 * The block editor needs live link previews without a REST round-trip on
	 * every keystroke, so it gets the templates — not a second copy of the
	 * resolving logic — and expands them with the same token contract.
	 *
	 * @return array
	 */
	public static function js_table() {
		$table = array();

		foreach ( self::types() as $slug => $def ) {
			$table[ $slug ] = array(
				'label'    => $def['label'],
				'scheme'   => $def['scheme'],
				'passthru' => $def['passthru'],
				'copyable' => (bool) $def['copyable'],
				'icon'     => self::icon( $slug ),
			);
		}

		return $table;
	}

	/**
	 * Type options for the editor's Contact Type dropdown.
	 *
	 * @return array[] List of { label, value } pairs.
	 */
	public static function options() {
		$options = array();

		foreach ( self::types() as $slug => $def ) {
			$options[] = array(
				'label' => $def['label'],
				'value' => $slug,
			);
		}

		return $options;
	}

	/**
	 * First resolved contact of a given type.
	 *
	 * @param array  $contacts Raw contacts attribute.
	 * @param string $type     Type slug.
	 * @return array|null
	 */
	public static function first( $contacts, $type ) {
		if ( ! is_array( $contacts ) ) {
			return null;
		}

		foreach ( $contacts as $contact ) {
			if ( ! is_array( $contact ) || ! isset( $contact['type'] ) ) {
				continue;
			}
			if ( strtolower( trim( (string) $contact['type'] ) ) !== $type ) {
				continue;
			}
			if ( ! isset( $contact['text'] ) || '' === trim( (string) $contact['text'] ) ) {
				continue;
			}

			return self::resolve( $contact );
		}

		return null;
	}
}

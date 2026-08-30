<?php
/**
 * vCard 3.0 builder.
 *
 * Produces an RFC 2426 compliant record from the normalised card model. This
 * is the only place the plugin writes vCard syntax: the download button, the
 * QR payload, the shortcode and the Elementor widget all call in here.
 *
 * @package BusinessCardBlock
 */

namespace BCB\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VCard {

	/**
	 * Physical line length before folding, in octets, per RFC 2426 §2.6.
	 */
	const FOLD_AT = 75;

	/**
	 * Build a vCard record from the normalised model.
	 *
	 * @param array $model Model::from_attributes() output.
	 * @param array $args  {
	 *     @type bool $compact Omit PHOTO and messaging extras. Used for QR
	 *                         payloads, where every byte raises the version
	 *                         and makes the code harder to scan.
	 * }
	 * @return string vCard text with CRLF line endings, or '' when the card
	 *                carries nothing worth exporting.
	 */
	public static function build( $model, $args = array() ) {
		$compact = ! empty( $args['compact'] );

		$identity = isset( $model['identity'] ) ? $model['identity'] : array();
		$contacts = isset( $model['contacts'] ) ? $model['contacts'] : array();

		$name = isset( $identity['name'] ) ? $identity['name'] : '';
		$lines = array();

		// A record with no name and no way to reach anyone is not a contact.
		if ( '' === $name && ! self::has_reachable( $contacts ) ) {
			return '';
		}

		$lines[] = 'BEGIN:VCARD';
		$lines[] = 'VERSION:3.0';

		if ( '' !== $name ) {
			$lines[] = 'FN:' . self::escape( $name );
			$lines[] = 'N:' . self::structured_name( $name );
		}

		if ( ! empty( $identity['title'] ) ) {
			$lines[] = 'TITLE:' . self::escape( $identity['title'] );
		}

		if ( ! empty( $identity['organization'] ) ) {
			$lines[] = 'ORG:' . self::escape( $identity['organization'] );
		}

		// PHOTO as a URI keeps the file small; embedding base64 would bloat
		// the record and break the QR payload entirely.
		if ( ! $compact && ! empty( $identity['avatar']['url'] ) ) {
			$lines[] = 'PHOTO;VALUE=URI:' . self::escape_uri( $identity['avatar']['url'] );
		}

		$types = Contacts::types();

		foreach ( $contacts as $contact ) {
			$type = isset( $contact['type'] ) ? $contact['type'] : '';
			$text = isset( $contact['text'] ) ? $contact['text'] : '';

			if ( '' === $text || ! isset( $types[ $type ] ) || empty( $types[ $type ]['vcard'] ) ) {
				continue;
			}

			$spec = $types[ $type ]['vcard'];

			// Messaging identifiers are extension properties; skipping them in
			// compact mode keeps the QR scannable on cheap phone cameras.
			$is_extension = ( 0 === strpos( $spec['prop'], 'X-' ) || 'IMPP' === $spec['prop'] );
			if ( $compact && $is_extension ) {
				continue;
			}

			$line = self::property( $spec, $contact );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		if ( ! $compact ) {
			foreach ( self::social_urls( $model ) as $url ) {
				$lines[] = 'X-SOCIALPROFILE;TYPE=other:' . self::escape_uri( $url );
			}
		}

		$lines[] = 'END:VCARD';

		$folded = array();
		foreach ( $lines as $line ) {
			$folded[] = self::fold( $line );
		}

		// RFC 2426 requires CRLF, and requires the record to end with one.
		return implode( "\r\n", $folded ) . "\r\n";
	}

	/**
	 * Render one content line for a contact row.
	 *
	 * @param array $spec    vCard spec from the type registry.
	 * @param array $contact Resolved contact row.
	 * @return string
	 */
	private static function property( $spec, $contact ) {
		$prop   = $spec['prop'];
		$params = isset( $spec['params'] ) ? $spec['params'] : '';
		$source = isset( $spec['value'] ) ? $spec['value'] : 'text';

		if ( 'href' === $source ) {
			$value = isset( $contact['href'] ) ? $contact['href'] : '';
			if ( '' === $value ) {
				return '';
			}
			$value = self::escape_uri( $value );
		} elseif ( ! empty( $spec['structured'] ) && 'adr' === $spec['structured'] ) {
			// A free-text address cannot be split into locality/region/country
			// reliably, so it goes in the street component where every client
			// displays it intact.
			$value = ';;' . self::escape( $contact['text'] ) . ';;;;';
		} else {
			$value = self::escape( $contact['text'] );
		}

		if ( '' === $value ) {
			return '';
		}

		return $prop . ( '' === $params ? '' : ';' . $params ) . ':' . $value;
	}

	/**
	 * Split a display name into vCard's five N components.
	 *
	 * @param string $name Full name.
	 * @return string Escaped "Family;Given;Additional;Prefix;Suffix".
	 */
	private static function structured_name( $name ) {
		$parts = preg_split( '/\s+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );

		if ( empty( $parts ) ) {
			return ';;;;';
		}

		if ( 1 === count( $parts ) ) {
			return ';' . self::escape( $parts[0] ) . ';;;';
		}

		$family = array_pop( $parts );
		$given  = array_shift( $parts );
		$middle = implode( ' ', $parts );

		return self::escape( $family ) . ';' . self::escape( $given ) . ';' . self::escape( $middle ) . ';;';
	}

	/**
	 * Collect social profile URLs from the model.
	 *
	 * @param array $model Normalised model.
	 * @return string[]
	 */
	private static function social_urls( $model ) {
		$urls = array();

		if ( empty( $model['socials'] ) || ! is_array( $model['socials'] ) ) {
			return $urls;
		}

		foreach ( $model['socials'] as $social ) {
			if ( ! empty( $social['link'] ) ) {
				$urls[] = $social['link'];
			}
		}

		return $urls;
	}

	/**
	 * Whether any contact row offers a way to reach the person.
	 *
	 * @param array $contacts Resolved contact rows.
	 * @return bool
	 */
	private static function has_reachable( $contacts ) {
		if ( ! is_array( $contacts ) ) {
			return false;
		}

		foreach ( $contacts as $contact ) {
			if ( ! empty( $contact['text'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Escape a TEXT value per RFC 2426 §5.
	 *
	 * Backslash first, or the escapes introduced below would be re-escaped.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function escape( $value ) {
		$value = (string) $value;

		// Any literal CR/LF in user input must become the \n escape, never a
		// raw line break — a stray newline silently truncates the record.
		$value = str_replace( array( "\r\n", "\r", "\n" ), '\n', $value );
		$value = str_replace( '\\', '\\\\', str_replace( '\n', "\x00", $value ) );
		$value = str_replace( "\x00", '\n', $value );
		$value = str_replace( array( ';', ',' ), array( '\;', '\,' ), $value );

		return $value;
	}

	/**
	 * Escape a URI value.
	 *
	 * URIs keep their slashes and colons; only the separators that would end
	 * the property need escaping.
	 *
	 * @param string $value Raw URI.
	 * @return string
	 */
	private static function escape_uri( $value ) {
		$value = str_replace( array( "\r", "\n" ), '', (string) $value );

		return str_replace( array( '\\', ';', ',' ), array( '\\\\', '\;', '\,' ), $value );
	}

	/**
	 * Fold a content line to 75 octets without splitting a UTF-8 sequence.
	 *
	 * @param string $line Unfolded content line.
	 * @return string
	 */
	private static function fold( $line ) {
		if ( strlen( $line ) <= self::FOLD_AT ) {
			return $line;
		}

		$out       = '';
		$remaining = $line;
		$limit     = self::FOLD_AT;

		while ( strlen( $remaining ) > $limit ) {
			$chunk = substr( $remaining, 0, $limit );

			// Walk back off a partial multi-byte character so the fold lands
			// on a codepoint boundary.
			$back = 0;
			while ( strlen( $chunk ) > 1 && $back < 4 ) {
				$next = ord( $remaining[ strlen( $chunk ) ] );
				if ( 0x80 !== ( $next & 0xC0 ) ) {
					break;
				}
				$chunk = substr( $chunk, 0, -1 );
				$back++;
			}

			$out      .= $chunk . "\r\n ";
			$remaining = substr( $remaining, strlen( $chunk ) );

			// Continuation lines carry a leading space, so they hold one octet
			// less of payload.
			$limit = self::FOLD_AT - 1;
		}

		return $out . $remaining;
	}

	/**
	 * A filesystem-safe .vcf filename for the card.
	 *
	 * @param array $model Normalised model.
	 * @return string
	 */
	public static function filename( $model ) {
		$name = '';

		if ( ! empty( $model['identity']['name'] ) ) {
			$name = $model['identity']['name'];
		} elseif ( ! empty( $model['identity']['organization'] ) ) {
			$name = $model['identity']['organization'];
		}

		$name = sanitize_file_name( $name );
		// sanitize_file_name leaves non-ASCII alone; strip it so the download
		// header stays predictable across browsers.
		$name = preg_replace( '/[^A-Za-z0-9._-]/', '-', $name );
		$name = trim( preg_replace( '/-+/', '-', $name ), '-.' );

		if ( '' === $name ) {
			$name = 'contact';
		}

		return substr( $name, 0, 60 ) . '.vcf';
	}
}

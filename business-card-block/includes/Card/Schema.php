<?php
/**
 * Schema.org JSON-LD builder.
 *
 * The card's visible markup is rendered client-side by React, so a crawler
 * that does not execute JavaScript sees an empty div. This class is what makes
 * the card's contents legible to search engines: it emits a server-rendered
 * JSON-LD block describing the same data the card shows.
 *
 * @package BusinessCardBlock
 */

namespace BCB\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema {

	/**
	 * Encoding flags for the JSON-LD payload.
	 *
	 * JSON_HEX_TAG is the important one: it turns "<" and ">" into < /
	 * > so a name containing "</script>" cannot break out of the script
	 * element. The other HEX flags close the equivalent quote/ampersand holes.
	 * Slashes stay escaped (JSON_UNESCAPED_SLASHES is deliberately not set)
	 * for the same reason.
	 */
	const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

	/**
	 * Build the JSON-LD graph for a card.
	 *
	 * @param array $model Model::from_attributes() output.
	 * @return array|null Associative array ready for encoding, or null when the
	 *                    card has nothing worth describing.
	 */
	public static function build( $model ) {
		$features = isset( $model['features']['schema'] ) ? $model['features']['schema'] : array();

		if ( empty( $features['enabled'] ) ) {
			return null;
		}

		$identity = isset( $model['identity'] ) ? $model['identity'] : array();
		$name     = isset( $identity['name'] ) ? $identity['name'] : '';
		$org      = isset( $identity['organization'] ) ? $identity['organization'] : '';

		$type = self::resolve_type( isset( $features['type'] ) ? $features['type'] : 'auto', $name, $org );

		if ( null === $type ) {
			return null;
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => $type,
		);

		// A Person is described by their own name; an Organization card with no
		// person's name is described by the company name.
		$data['name'] = ( 'Person' === $type && '' !== $name ) ? $name : ( '' !== $org ? $org : $name );

		if ( '' === $data['name'] ) {
			return null;
		}

		if ( 'Person' === $type ) {
			if ( ! empty( $identity['title'] ) ) {
				$data['jobTitle'] = $identity['title'];
			}
			if ( '' !== $org ) {
				$data['worksFor'] = array(
					'@type' => 'Organization',
					'name'  => $org,
				);
			}
		} elseif ( ! empty( $identity['title'] ) && 'Organization' !== $type ) {
			// LocalBusiness has no jobTitle; the role reads better as a slogan.
			$data['description'] = $identity['title'];
		}

		if ( ! empty( $identity['avatar']['url'] ) ) {
			$data['image'] = $identity['avatar']['url'];
		} elseif ( ! empty( $identity['logo']['url'] ) ) {
			$data['image'] = $identity['logo']['url'];
		}

		self::add_contacts( $data, $model, $type );
		self::add_socials( $data, $model );

		// A bare name and nothing else is noise for search engines; only emit
		// the block once it actually carries contact detail.
		if ( count( $data ) <= 3 ) {
			return null;
		}

		return $data;
	}

	/**
	 * Map contact rows onto schema properties.
	 *
	 * @param array $data  Schema payload, modified in place.
	 * @param array $model Normalised model.
	 * @param string $type Resolved schema type.
	 * @return void
	 */
	private static function add_contacts( &$data, $model, $type ) {
		if ( empty( $model['contacts'] ) || ! is_array( $model['contacts'] ) ) {
			return;
		}

		$types = Contacts::types();

		foreach ( $model['contacts'] as $contact ) {
			$slug = isset( $contact['type'] ) ? $contact['type'] : '';
			$text = isset( $contact['text'] ) ? $contact['text'] : '';

			if ( '' === $text || ! isset( $types[ $slug ] ) || empty( $types[ $slug ]['schema'] ) ) {
				continue;
			}

			$prop = $types[ $slug ]['schema'];

			// Never overwrite: the first contact of each kind wins, matching
			// what a reader sees at the top of the card.
			if ( isset( $data[ $prop ] ) ) {
				continue;
			}

			if ( 'address' === $prop ) {
				$data['address'] = array(
					'@type'         => 'PostalAddress',
					'streetAddress' => $text,
				);
				continue;
			}

			if ( 'url' === $prop ) {
				// url must be a resolvable URL, not the display text.
				if ( ! empty( $contact['href'] ) ) {
					$data['url'] = $contact['href'];
				}
				continue;
			}

			$data[ $prop ] = $text;
		}

		// LocalBusiness requires an address to validate; downgrade rather than
		// emit an invalid entity.
		if ( 'LocalBusiness' === $type && ! isset( $data['address'] ) ) {
			$data['@type'] = 'Organization';
		}
	}

	/**
	 * Attach social profile URLs as sameAs.
	 *
	 * @param array $data  Schema payload, modified in place.
	 * @param array $model Normalised model.
	 * @return void
	 */
	private static function add_socials( &$data, $model ) {
		if ( empty( $model['socials'] ) || ! is_array( $model['socials'] ) ) {
			return;
		}

		$same_as = array();
		foreach ( $model['socials'] as $social ) {
			if ( ! empty( $social['link'] ) ) {
				$same_as[] = $social['link'];
			}
		}

		$same_as = array_values( array_unique( $same_as ) );

		if ( ! empty( $same_as ) ) {
			$data['sameAs'] = $same_as;
		}
	}

	/**
	 * Decide which Schema.org type describes this card.
	 *
	 * @param string $requested Configured type ( auto | person | organization | localbusiness | none ).
	 * @param string $name      Person name.
	 * @param string $org       Organization name.
	 * @return string|null Schema type, or null to emit nothing.
	 */
	private static function resolve_type( $requested, $name, $org ) {
		switch ( $requested ) {
			case 'none':
				return null;
			case 'person':
				return 'Person';
			case 'organization':
				return 'Organization';
			case 'localbusiness':
				return 'LocalBusiness';
		}

		// Auto: a card with a person's name is a Person who may work for an
		// Organization. Without one, the card is describing the company.
		if ( '' !== $name ) {
			return 'Person';
		}

		return '' !== $org ? 'Organization' : null;
	}

	/**
	 * Render the complete <script type="application/ld+json"> element.
	 *
	 * @param array $model Normalised model.
	 * @return string Markup, or '' when there is no schema to emit.
	 */
	public static function render( $model ) {
		$data = self::build( $model );

		if ( empty( $data ) ) {
			return '';
		}

		$json = wp_json_encode( $data, self::JSON_FLAGS );

		if ( false === $json ) {
			return '';
		}

		return '<script type="application/ld+json">' . $json . '</script>';
	}
}

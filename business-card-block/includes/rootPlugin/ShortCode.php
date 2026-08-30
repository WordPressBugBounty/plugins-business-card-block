<?php
/**
 * [bcb] shortcode.
 *
 * Renders a stored Business Card through the exact same block render path
 * Gutenberg uses, so themes, contact links, QR, vCard and schema all behave
 * identically however the card reaches the page.
 *
 * @package BusinessCardBlock
 */

namespace BCB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcode {

	/**
	 * Attributes a shortcode may override on the stored card.
	 *
	 * Everything else is taken from the saved block so the card keeps looking
	 * the way it was designed.
	 *
	 * @var array Attribute name => sanitiser.
	 */
	private $overridable = array(
		'theme'     => 'slug',
		'name'      => 'text',
		'title'     => 'text',
		'width'     => 'text',
		'alignment' => 'slug',
	);

	/**
	 * Contact types whose text a shortcode may replace.
	 *
	 * Only existing rows are updated — a card cannot grow a new contact from a
	 * shortcode, because the row would have no icon to render.
	 *
	 * @var string[]
	 */
	private $contact_atts = array( 'address', 'phone', 'email', 'website', 'whatsapp', 'telegram', 'imo', 'skype', 'wechat' );

	function __construct() {
		add_shortcode( 'bcb', array( $this, 'onAddShortcode' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	function onAddShortcode( $atts ) {
		$atts = shortcode_atts(
			array_merge(
				array( 'id' => 0, 'company' => '', 'tagline' => '', 'qr' => '', 'vcard' => '', 'schema' => '' ),
				array_fill_keys( array_keys( $this->overridable ), '' ),
				array_fill_keys( $this->contact_atts, '' )
			),
			is_array( $atts ) ? $atts : array(),
			'bcb'
		);

		$post_id = absint( $atts['id'] );

		if ( ! $post_id ) {
			return $this->notice( __( 'Please provide a Business Card ID, for example [bcb id="12"].', 'business-card-block' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post || 'bcb' !== $post->post_type ) {
			/* translators: %d: the requested card ID. */
			return $this->notice( sprintf( __( 'Business Card %d was not found.', 'business-card-block' ), $post_id ) );
		}

		if ( ! $this->can_read( $post ) ) {
			return '';
		}

		if ( post_password_required( $post ) ) {
			return get_the_password_form( $post );
		}

		$blocks = parse_blocks( $post->post_content );

		if ( empty( $blocks ) ) {
			return '';
		}

		$overrides = $this->collect_overrides( $atts );

		$out = '';
		foreach ( $blocks as $block ) {
			// parse_blocks emits a nameless block for the whitespace between
			// blocks; rendering those produced the stray-blank-output bug.
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( ! empty( $overrides ) && $this->is_card_block( $block['blockName'] ) ) {
				$block = $this->apply_overrides( $block, $overrides );
			}

			$out .= render_block( $block );
		}

		return $out;
	}

	/**
	 * Whether the current visitor may see this card.
	 *
	 * Without this check, [bcb id="N"] would render drafts and privately
	 * published cards to anyone who could place a shortcode.
	 *
	 * @param \WP_Post $post Card post.
	 * @return bool
	 */
	private function can_read( $post ) {
		switch ( $post->post_status ) {
			case 'publish':
				return true;

			case 'private':
				return current_user_can( 'read_post', $post->ID );

			case 'draft':
			case 'pending':
			case 'future':
			case 'auto-draft':
				return current_user_can( 'edit_post', $post->ID );

			default:
				return false;
		}
	}

	/**
	 * Whether a block name belongs to this plugin.
	 *
	 * @param string $name Block name.
	 * @return bool
	 */
	private function is_card_block( $name ) {
		return 0 === strpos( $name, 'business/' );
	}

	/**
	 * Turn shortcode attributes into a sanitised override map.
	 *
	 * @param array $atts Parsed shortcode attributes.
	 * @return array
	 */
	private function collect_overrides( $atts ) {
		$overrides = array();

		foreach ( $this->overridable as $key => $kind ) {
			if ( '' === $atts[ $key ] ) {
				continue;
			}

			$overrides[ $key ] = 'slug' === $kind
				? preg_replace( '/[^a-z0-9_-]/', '', strtolower( $atts[ $key ] ) )
				: sanitize_text_field( $atts[ $key ] );
		}

		foreach ( array( 'company', 'tagline' ) as $key ) {
			if ( '' !== $atts[ $key ] ) {
				$overrides['businessCard'][ $key ] = sanitize_text_field( $atts[ $key ] );
			}
		}

		foreach ( array( 'qr' => 'qr', 'vcard' => 'vcard', 'schema' => 'schema' ) as $att => $feature ) {
			if ( '' === $atts[ $att ] ) {
				continue;
			}
			$overrides['features'][ $feature ] = \BCB\Card\Model::bool( $atts[ $att ] );
		}

		foreach ( $this->contact_atts as $type ) {
			if ( '' !== $atts[ $type ] ) {
				$overrides['contacts'][ $type ] = sanitize_text_field( $atts[ $type ] );
			}
		}

		return $overrides;
	}

	/**
	 * Merge overrides into a parsed block's attributes.
	 *
	 * @param array $block     Parsed block.
	 * @param array $overrides Sanitised overrides.
	 * @return array Modified block.
	 */
	private function apply_overrides( $block, $overrides ) {
		if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
			$block['attrs'] = array();
		}

		foreach ( $this->overridable as $key => $unused ) {
			if ( isset( $overrides[ $key ] ) ) {
				$block['attrs'][ $key ] = $overrides[ $key ];
			}
		}

		if ( isset( $overrides['businessCard'] ) ) {
			$existing                 = isset( $block['attrs']['businessCard'] ) && is_array( $block['attrs']['businessCard'] )
				? $block['attrs']['businessCard']
				: array();
			$block['attrs']['businessCard'] = array_merge( $existing, $overrides['businessCard'] );

			// The industry blocks read company and tagline from the top level.
			foreach ( $overrides['businessCard'] as $key => $value ) {
				if ( isset( $block['attrs'][ $key ] ) || 0 === strpos( (string) $block['blockName'], 'business/' ) ) {
					$block['attrs'][ $key ] = $value;
				}
			}
		}

		if ( isset( $overrides['features']['vcard'] ) ) {
			$existing                       = isset( $block['attrs']['businessCard'] ) && is_array( $block['attrs']['businessCard'] )
				? $block['attrs']['businessCard']
				: array();
			$existing['isDownloadBtn']      = $overrides['features']['vcard'];
			$block['attrs']['businessCard'] = $existing;
		}

		if ( isset( $overrides['features']['qr'] ) ) {
			$existing               = isset( $block['attrs']['qr'] ) && is_array( $block['attrs']['qr'] ) ? $block['attrs']['qr'] : array();
			$existing['enable']     = $overrides['features']['qr'];
			$block['attrs']['qr']   = $existing;
		}

		if ( isset( $overrides['features']['schema'] ) ) {
			$block['attrs']['schemaEnabled'] = $overrides['features']['schema'];
		}

		if ( isset( $overrides['contacts'] ) ) {
			$block['attrs']['contacts'] = $this->override_contacts(
				isset( $block['attrs']['contacts'] ) ? $block['attrs']['contacts'] : array(),
				$overrides['contacts']
			);
		}

		return $block;
	}

	/**
	 * Replace the text of contact rows whose type was overridden.
	 *
	 * @param array $contacts  Saved contacts.
	 * @param array $overrides type => text.
	 * @return array
	 */
	private function override_contacts( $contacts, $overrides ) {
		if ( ! is_array( $contacts ) ) {
			return array();
		}

		foreach ( $contacts as $index => $contact ) {
			if ( ! is_array( $contact ) || empty( $contact['type'] ) ) {
				continue;
			}

			$type = strtolower( trim( (string) $contact['type'] ) );

			if ( isset( $overrides[ $type ] ) ) {
				$contacts[ $index ]['text'] = $overrides[ $type ];
			}
		}

		return $contacts;
	}

	/**
	 * A visible message, shown only to users who can act on it.
	 *
	 * Visitors get nothing rather than a broken-looking error.
	 *
	 * @param string $message Message text.
	 * @return string
	 */
	private function notice( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return '<p class="bcb-shortcode-notice">' . esc_html( $message ) . '</p>';
	}
}

<?php
/**
 * Editor preview endpoint.
 *
 * The block editor needs the same QR image and vCard text the frontend gets,
 * but PHP does not run during editing. Rather than reimplement the encoder in
 * JavaScript, the editor asks for a preview here — so QR, vCard and schema
 * have exactly one implementation, in PHP.
 *
 * @package BusinessCardBlock
 */

namespace BCB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest {

	const NAMESPACE = 'business-card/v1';

	function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the preview route.
	 *
	 * @return void
	 */
	function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_preview' ),
				'args'                => array(
					'attributes' => array(
						'required'    => true,
						'type'        => 'object',
						'description' => __( 'Business Card block attributes.', 'business-card-block' ),
					),
				),
			)
		);
	}

	/**
	 * Only users who can author content may render a preview.
	 *
	 * The endpoint reflects caller-supplied attributes rather than reading
	 * stored data, so this gate is about not handing anonymous visitors a free
	 * QR-encoding service, not about protecting anyone's content.
	 *
	 * @return bool|\WP_Error
	 */
	function can_preview() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'bcb_forbidden',
				__( 'You are not allowed to preview Business Cards.', 'business-card-block' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Build a preview payload for the supplied attributes.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	function preview( $request ) {
		$attributes = $request->get_param( 'attributes' );

		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$model = \BCB\Card\Model::from_attributes( $attributes );

		$response = array(
			'contacts' => array(),
			'vcard'    => null,
			'qr'       => null,
			'schema'   => \BCB\Card\Schema::build( $model ),
		);

		foreach ( $model['contacts'] as $contact ) {
			$response['contacts'][] = array(
				'href'     => $contact['href'],
				'copyable' => $contact['copyable'],
				'label'    => $contact['label'],
			);
		}

		$vcard = \BCB\Card\VCard::build( $model );

		if ( '' !== $vcard ) {
			$response['vcard'] = array(
				'text'     => $vcard,
				'filename' => \BCB\Card\VCard::filename( $model ),
			);
		}

		$qr = $model['features']['qr'];

		if ( ! empty( $qr['enabled'] ) ) {
			$response['qr'] = array(
				'svg'     => $this->qr_svg( $model, $qr, $vcard ),
				'caption' => $qr['caption'],
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Encode the QR for a preview.
	 *
	 * Mirrors the frontend's source selection so what the editor shows is what
	 * the visitor scans.
	 *
	 * @param array  $model Normalised model.
	 * @param array  $qr    QR settings.
	 * @param string $vcard Full vCard text.
	 * @return string
	 */
	private function qr_svg( $model, $qr, $vcard ) {
		switch ( $qr['source'] ) {
			case 'url':
				// The post being edited may not have a permalink yet, so the
				// preview falls back to the site address.
				$text = home_url( '/' );
				break;
			case 'custom':
				$text = $qr['custom'];
				break;
			default:
				$text = \BCB\Card\VCard::build( $model, array( 'compact' => true ) );
				if ( '' === $text ) {
					$text = $vcard;
				}
				break;
		}

		if ( '' === trim( (string) $text ) ) {
			return '';
		}

		$svg = \BCB\Card\QR::svg(
			$text,
			array(
				'size'  => $qr['size'],
				'dark'  => $qr['dark'],
				'light' => $qr['light'],
				'ecc'   => $qr['ecc'],
				'title' => __( 'Business card QR code preview', 'business-card-block' ),
			)
		);

		return is_wp_error( $svg ) ? '' : $svg;
	}
}

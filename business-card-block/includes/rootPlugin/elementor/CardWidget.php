<?php
/**
 * Business Card Elementor widget.
 *
 * Builds a block-shaped attributes array from its controls and hands it to the
 * shared renderer, so the widget inherits contact links, vCard, QR and
 * Schema.org output without reimplementing any of them.
 *
 * @package BusinessCardBlock
 */

namespace BCB\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

class CardWidget extends Widget_Base {

	public function get_name() {
		return 'bcb-business-card';
	}

	public function get_title() {
		return __( 'Business Card', 'business-card-block' );
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_categories() {
		return array( \BCB\Elementor::CATEGORY );
	}

	public function get_keywords() {
		return array( 'business', 'card', 'vcard', 'contact', 'qr' );
	}

	/**
	 * Elementor caches widget output; a card's QR and vCard depend only on its
	 * own settings, so per-widget caching is safe. Declaring the script handle
	 * keeps the frontend bundle loading in the editor preview.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		$handle = \BCB\Elementor::view_script_handle();

		return $handle ? array( $handle ) : array();
	}

	protected function register_controls() {
		$this->register_source_controls();
		$this->register_identity_controls();
		$this->register_contact_controls();
		$this->register_feature_controls();
	}

	/**
	 * Where the card's data comes from.
	 *
	 * @return void
	 */
	private function register_source_controls() {
		$this->start_controls_section(
			'bcb_source',
			array( 'label' => __( 'Card', 'business-card-block' ) )
		);

		$this->add_control(
			'source',
			array(
				'label'   => __( 'Card Source', 'business-card-block' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'saved',
				'options' => array(
					'saved'  => __( 'A saved Business Card', 'business-card-block' ),
					'custom' => __( 'Build it here', 'business-card-block' ),
				),
			)
		);

		$this->add_control(
			'card_id',
			array(
				'label'       => __( 'Business Card', 'business-card-block' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $this->card_options(),
				'default'     => '',
				'description' => __( 'Cards are managed under Business Cards in the admin menu.', 'business-card-block' ),
				'condition'   => array( 'source' => 'saved' ),
			)
		);

		$this->add_control(
			'theme',
			array(
				'label'     => __( 'Design', 'business-card-block' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'default',
				'options'   => array(
					'default'    => __( 'Default', 'business-card-block' ),
					'minimal_qr' => __( 'Minimal QR', 'business-card-block' ),
					'theme1'     => __( 'Theme 1', 'business-card-block' ),
					'theme2'     => __( 'Theme 2', 'business-card-block' ),
					'theme3'     => __( 'Theme 3', 'business-card-block' ),
				),
				'condition' => array( 'source' => 'custom' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Name, role, company, avatar.
	 *
	 * @return void
	 */
	private function register_identity_controls() {
		$this->start_controls_section(
			'bcb_identity',
			array(
				'label'     => __( 'Identity', 'business-card-block' ),
				'condition' => array( 'source' => 'custom' ),
			)
		);

		$this->add_control(
			'name',
			array(
				'label'   => __( 'Name', 'business-card-block' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Jonathon Doe', 'business-card-block' ),
			)
		);

		$this->add_control(
			'job_title',
			array(
				'label'   => __( 'Job Title', 'business-card-block' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Chief Executive Officer', 'business-card-block' ),
			)
		);

		$this->add_control(
			'company',
			array(
				'label' => __( 'Organization', 'business-card-block' ),
				'type'  => Controls_Manager::TEXT,
			)
		);

		$this->add_control(
			'avatar',
			array(
				'label' => __( 'Avatar', 'business-card-block' ),
				'type'  => Controls_Manager::MEDIA,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * The contact repeater, covering both plain contacts and messaging apps.
	 *
	 * @return void
	 */
	private function register_contact_controls() {
		$this->start_controls_section(
			'bcb_contacts',
			array(
				'label'     => __( 'Contact Methods', 'business-card-block' ),
				'condition' => array( 'source' => 'custom' ),
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'type',
			array(
				'label'   => __( 'Type', 'business-card-block' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'phone',
				'options' => $this->contact_type_options(),
			)
		);

		$repeater->add_control(
			'text',
			array(
				'label'       => __( 'Value', 'business-card-block' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'description' => __( 'A phone number, email, URL, @username or app ID depending on the type.', 'business-card-block' ),
			)
		);

		$this->add_control(
			'contacts',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ type }}}: {{{ text }}}',
				'default'     => array(
					array( 'type' => 'phone', 'text' => '+000 0000-00000' ),
					array( 'type' => 'email', 'text' => 'example@mail.com' ),
				),
			)
		);

		$this->add_control(
			'contact_links',
			array(
				'label'        => __( 'Clickable contacts', 'business-card-block' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'description'  => __( 'Turns rows into tel:, mailto: and app links. IMO and WeChat get a copy button.', 'business-card-block' ),
				'return_value' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * QR, vCard download and structured data.
	 *
	 * @return void
	 */
	private function register_feature_controls() {
		$this->start_controls_section(
			'bcb_features',
			array(
				'label'     => __( 'QR, vCard & SEO', 'business-card-block' ),
				'condition' => array( 'source' => 'custom' ),
			)
		);

		$this->add_control(
			'qr_enable',
			array(
				'label'        => __( 'Show QR code', 'business-card-block' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'qr_source',
			array(
				'label'     => __( 'QR encodes', 'business-card-block' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'vcard',
				'options'   => array(
					'vcard'  => __( 'Contact details (vCard)', 'business-card-block' ),
					'url'    => __( "This page's URL", 'business-card-block' ),
					'custom' => __( 'Custom URL', 'business-card-block' ),
				),
				'condition' => array( 'qr_enable' => 'yes' ),
			)
		);

		$this->add_control(
			'qr_custom',
			array(
				'label'     => __( 'Custom URL', 'business-card-block' ),
				'type'      => Controls_Manager::URL,
				'condition' => array( 'qr_enable' => 'yes', 'qr_source' => 'custom' ),
			)
		);

		$this->add_control(
			'qr_size',
			array(
				'label'     => __( 'QR size (px)', 'business-card-block' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array( 'size' => 140 ),
				'range'     => array( 'px' => array( 'min' => 64, 'max' => 512, 'step' => 4 ) ),
				'condition' => array( 'qr_enable' => 'yes' ),
			)
		);

		$this->add_control(
			'vcard_enable',
			array(
				'label'        => __( 'Show download button', 'business-card-block' ),
				'type'         => Controls_Manager::SWITCHER,
				'separator'    => 'before',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'vcard_label',
			array(
				'label'     => __( 'Button label', 'business-card-block' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Save Contact', 'business-card-block' ),
				'condition' => array( 'vcard_enable' => 'yes' ),
			)
		);

		$this->add_control(
			'schema_enable',
			array(
				'label'        => __( 'Output Schema.org markup', 'business-card-block' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'separator'    => 'before',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'schema_type',
			array(
				'label'     => __( 'Describe as', 'business-card-block' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'auto',
				'options'   => array(
					'auto'          => __( 'Automatic', 'business-card-block' ),
					'person'        => __( 'Person', 'business-card-block' ),
					'organization'  => __( 'Organization', 'business-card-block' ),
					'localbusiness' => __( 'Local Business', 'business-card-block' ),
				),
				'condition' => array( 'schema_enable' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		\BCB\Elementor::enqueue_card_assets();

		if ( 'saved' === ( isset( $settings['source'] ) ? $settings['source'] : 'saved' ) ) {
			$this->render_saved_card( $settings );
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Render::card escapes internally.
		echo \BCB\Card\Render::card(
			$this->attributes_from_settings( $settings ),
			array(
				'prefix'  => 'bcbBusinessCard-',
				'wrapper' => 'class="wp-block-business-card bcb-elementor-card"',
			)
		);
	}

	/**
	 * Render a card stored in the Business Cards post type.
	 *
	 * Goes through the shortcode so the access rules that protect drafts and
	 * private cards apply here too.
	 *
	 * @param array $settings Widget settings.
	 * @return void
	 */
	private function render_saved_card( $settings ) {
		$card_id = isset( $settings['card_id'] ) ? absint( $settings['card_id'] ) : 0;

		if ( ! $card_id ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'Choose a Business Card to display.', 'business-card-block' ) . '</p>';
			}
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block render output.
		echo do_shortcode( '[bcb id="' . $card_id . '"]' );
	}

	/**
	 * Translate widget settings into block attributes.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function attributes_from_settings( $settings ) {
		$contacts = array();

		if ( ! empty( $settings['contacts'] ) && is_array( $settings['contacts'] ) ) {
			foreach ( $settings['contacts'] as $row ) {
				$type = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : '';
				$text = isset( $row['text'] ) ? sanitize_text_field( $row['text'] ) : '';

				if ( '' === $type || '' === $text ) {
					continue;
				}

				$contacts[] = array(
					'type' => $type,
					'text' => $text,
					// The widget has no icon picker, so each row gets its
					// type's default glyph from the shared registry.
					'icon' => array( 'svg' => \BCB\Card\Contacts::icon( $type ) ),
				);
			}
		}

		$qr_custom = '';
		if ( ! empty( $settings['qr_custom']['url'] ) ) {
			$qr_custom = $settings['qr_custom']['url'];
		}

		$avatar_url = ! empty( $settings['avatar']['url'] ) ? $settings['avatar']['url'] : '';

		return array(
			'name'          => isset( $settings['name'] ) ? $settings['name'] : '',
			'title'         => isset( $settings['job_title'] ) ? $settings['job_title'] : '',
			'theme'         => isset( $settings['theme'] ) ? $settings['theme'] : 'default',
			'contacts'      => $contacts,
			'contactLinks'  => 'yes' === ( isset( $settings['contact_links'] ) ? $settings['contact_links'] : 'yes' ),
			'showAvatar'    => '' !== $avatar_url,
			'avatar'        => array(
				'url'  => $avatar_url,
				'id'   => ! empty( $settings['avatar']['id'] ) ? absint( $settings['avatar']['id'] ) : 0,
				'mask' => 'circle',
				'size' => 80,
			),
			'businessCard'  => array(
				'company'       => isset( $settings['company'] ) ? $settings['company'] : '',
				'isDownloadBtn' => 'yes' === ( isset( $settings['vcard_enable'] ) ? $settings['vcard_enable'] : '' ),
				'downloadLabel' => isset( $settings['vcard_label'] ) ? $settings['vcard_label'] : '',
				'height'        => '0px',
			),
			'qr'            => array(
				'enable' => 'yes' === ( isset( $settings['qr_enable'] ) ? $settings['qr_enable'] : '' ),
				'source' => isset( $settings['qr_source'] ) ? $settings['qr_source'] : 'vcard',
				'custom' => $qr_custom,
				'size'   => isset( $settings['qr_size']['size'] ) ? absint( $settings['qr_size']['size'] ) : 140,
				'ecc'    => 'M',
			),
			'schemaEnabled' => 'yes' === ( isset( $settings['schema_enable'] ) ? $settings['schema_enable'] : 'yes' ),
			'schemaType'    => isset( $settings['schema_type'] ) ? $settings['schema_type'] : 'auto',
		);
	}

	/**
	 * Published Business Cards, for the picker.
	 *
	 * @return array id => title.
	 */
	private function card_options() {
		$options = array( '' => __( '— Select —', 'business-card-block' ) );

		$cards = get_posts(
			array(
				'post_type'        => 'bcb',
				'post_status'      => 'publish',
				'numberposts'      => 100,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $cards as $card ) {
			$options[ $card->ID ] = $card->post_title ? $card->post_title : sprintf(
				/* translators: %d: card ID. */
				__( 'Card #%d', 'business-card-block' ),
				$card->ID
			);
		}

		return $options;
	}

	/**
	 * Contact types, read from the shared registry.
	 *
	 * @return array slug => label.
	 */
	private function contact_type_options() {
		$options = array();

		foreach ( \BCB\Card\Contacts::options() as $option ) {
			$options[ $option['value'] ] = $option['label'];
		}

		return $options;
	}
}

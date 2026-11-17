<?php
/**
 * bPlugins BFCM 2026 Promotional Module
 *
 * Reusable admin promotion for bPlugins products.
 * PHP 7.4+
 *
 * Include this file from the main plugin file, then call
 * bplugins_bfcm2026_init() with product-specific settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bplugins_bfcm2026_init' ) ) {

	/**
	 * Register the BFCM campaign for one plugin.
	 *
	 * Required config:
	 * - plugin_file: plugin basename, normally plugin_basename( __FILE__ )
	 * - pricing_url: product pricing page URL, without campaign UTM parameters
	 *
	 * Dashboard config:
	 * - dashboard_screen_ids: array of WP screen IDs for the product dashboard
	 *
	 * Optional display config:
	 * - product_name, logo_url, title, description, badge_text, features,
	 *   button_text, action_text, primary_color, secondary_color,
	 *   accent_color, text_color, campaign_end
	 */
	function bplugins_bfcm2026_init( $config ) {
		$defaults = array(
			'plugin_file'          => '',
			'pricing_url'          => '',
			'dashboard_screen_ids' => array(),
			'product_name'         => 'bPlugins Product',
			'logo_url'             => '',
			'title'                => 'Upgrade to {product} Pro',
			'description'          => 'Unlock more powerful features with our Black Friday offer.',
			'badge_text'           => 'Flat 45% OFF',
			'features'             => array(),
			'button_text'          => 'Unlock PRO Features',
			'action_text'          => 'Upgrade to Pro and get 45% off on this Black Friday',
			'primary_color'        => '#1d4ed8',
			'secondary_color'      => '#0f172a',
			'accent_color'         => '#ff6b35',
			'text_color'           => '#172033',
			'campaign_end'         => '2026-12-05 23:59:59',
		);

		$config = wp_parse_args( $config, $defaults );

		if ( empty( $config['plugin_file'] ) || empty( $config['pricing_url'] ) ) {
			return;
		}

		$config['plugin_file'] = plugin_basename( $config['plugin_file'] );
		$config['dashboard_screen_ids'] = array_values( (array) $config['dashboard_screen_ids'] );
		$config['features'] = array_values( (array) $config['features'] );

		$promo = new BPlugins_BFCM2026_Promo( $config );
		$promo->register();
	}

	/**
	 * Campaign implementation.
	 */
	class BPlugins_BFCM2026_Promo {

		private $config;
		private $dismiss_meta_key = 'bplugins_bfcm2026_dashboard_dismissed';

		public function __construct( $config ) {
			$this->config = $config;
		}

		public function register() {
			add_action( 'admin_init', array( $this, 'register_ajax' ) );
			add_action( 'admin_notices', array( $this, 'render_dashboard_banner' ) );
			add_filter(
				'plugin_action_links_' . $this->config['plugin_file'],
				array( $this, 'add_plugin_row_link' ),
				20,
				1
			);
		}

		public function register_ajax() {
			add_action( 'wp_ajax_bplugins_bfcm2026_dismiss', array( $this, 'ajax_dismiss' ) );
		}

		private function campaign_is_active() {
			$end = strtotime( $this->config['campaign_end'] . ' ' . wp_timezone_string() );

			if ( ! $end ) {
				return false;
			}

			return current_time( 'timestamp' ) <= $end;
		}

		private function is_dashboard_screen() {
			if ( empty( $this->config['dashboard_screen_ids'] ) ) {
				return false;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			return $screen && in_array( $screen->id, $this->config['dashboard_screen_ids'], true );
		}

		private function is_dismissed() {
			return (bool) get_user_meta( get_current_user_id(), $this->dismiss_meta_key, true );
		}

		private function get_pricing_url( $medium ) {
			$url = add_query_arg(
				array(
					'utm_source'   => 'admin',
					'utm_medium'   => $medium,
					'utm_campaign' => 'bfcm2026',
				),
				$this->config['pricing_url']
			);

			return esc_url( $url );
		}

		private function get_title() {
			return str_replace( '{product}', $this->config['product_name'], $this->config['title'] );
		}

		/**
		 * Dashboard banner.
		 */
		public function render_dashboard_banner() {
			if ( ! $this->campaign_is_active() || ! $this->is_dashboard_screen() || $this->is_dismissed() ) {
				return;
			}

			$config = $this->config;
			$features = $config['features'];
			?>
			<div class="bplugins-bfcm-banner" role="region" aria-label="<?php echo esc_attr( $config['product_name'] . ' Black Friday promotion' ); ?>">
				<div class="bplugins-bfcm-brand" style="--bfcm-primary:<?php echo esc_attr( $config['primary_color'] ); ?>;--bfcm-secondary:<?php echo esc_attr( $config['secondary_color'] ); ?>">
					<?php if ( ! empty( $config['logo_url'] ) ) : ?>
						<img src="<?php echo esc_url( $config['logo_url'] ); ?>" alt="" />
					<?php else : ?>
						<span class="bplugins-bfcm-brand-mark" aria-hidden="true"><?php echo esc_html( strtoupper( substr( $config['product_name'], 0, 1 ) ) ); ?></span>
					<?php endif; ?>
					<strong><?php echo esc_html( $config['product_name'] ); ?></strong>
				</div>

				<div class="bplugins-bfcm-content">
					<div class="bplugins-bfcm-heading">
						<strong><?php echo esc_html( $this->get_title() ); ?></strong>
						<span class="bplugins-bfcm-badge" style="--bfcm-accent:<?php echo esc_attr( $config['accent_color'] ); ?>">
							<?php echo esc_html( $config['badge_text'] ); ?>
						</span>
					</div>
					<p><?php echo esc_html( $config['description'] ); ?></p>

					<?php if ( ! empty( $features ) ) : ?>
						<ul class="bplugins-bfcm-features">
							<?php foreach ( $features as $feature ) : ?>
								<li><?php echo esc_html( $feature ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<a class="bplugins-bfcm-button" href="<?php echo $this->get_pricing_url( 'dashboard' ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $config['button_text'] ); ?> <span aria-hidden="true">→</span>
				</a>

				<button type="button" class="bplugins-bfcm-dismiss" aria-label="<?php esc_attr_e( 'Dismiss promotion', 'bplugins' ); ?>">×</button>
			</div>
			<?php
			$this->print_dashboard_assets();
		}

		/**
		 * Add the promotional action link only to this plugin's row.
		 */
		public function add_plugin_row_link( $links ) {
			if ( ! $this->campaign_is_active() ) {
				return $links;
			}

			$label = sprintf(
				'<span class="bplugins-bfcm-row-link" style="color:#d63638;font-weight:bold;">%s</span>',
				esc_html( $this->config['action_text'] )
			);

			$links[] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				$this->get_pricing_url( 'plugins' ),
				$label
			);

			return $links;
		}

		/**
		 * Inline dashboard CSS/JS is printed only when the banner is rendered.
		 */
		private function print_dashboard_assets() {
			?>
			<style id="bplugins-bfcm2026-dashboard-css">
				.bplugins-bfcm-banner {
					--bfcm-primary: #1d4ed8;
					--bfcm-secondary: #0f172a;
					--bfcm-accent: #ff6b35;
					position: relative;
					display: flex;
					align-items: center;
					gap: 22px;
					box-sizing: border-box;
					width: 100%;
					margin: 14px 0 20px;
					padding: 14px 54px 14px 14px;
					background: #fff;
					border: 1px solid #d8e0ec;
					border-radius: 10px;
					box-shadow: 0 2px 8px rgba(15, 23, 42, .06);
					color: <?php echo esc_html( $this->config['text_color'] ); ?>;
				}
				
           .bplugins-bfcm-banner .bplugins-bfcm-row-link {
          color: red !important;
         font-weight: 600;
      }
				.bplugins-bfcm-brand {
					align-self: stretch;
					flex: 0 0 190px;
					min-height: 86px;
					display: flex;
					flex-direction: column;
					align-items: center;
					justify-content: center;
					gap: 7px;
					margin: -14px 0 -14px -14px;
					padding: 12px;
					border-radius: 9px 0 0 9px;
					background: linear-gradient(135deg, var(--bfcm-primary), var(--bfcm-secondary));
					color: #fff;
					text-align: center;
				}
				.bplugins-bfcm-brand img {
					display: block;
					max-width: 54px;
					max-height: 44px;
					object-fit: contain;
				}
				.bplugins-bfcm-brand-mark {
					display: grid;
					width: 38px;
					height: 38px;
					place-items: center;
					border-radius: 10px;
					background: rgba(255,255,255,.18);
					font-size: 22px;
					font-weight: 800;
				}
				.bplugins-bfcm-brand strong {
					font-size: 12px;
					letter-spacing: .08em;
					text-transform: uppercase;
				}
				.bplugins-bfcm-content { flex: 1 1 auto; min-width: 0; }
				.bplugins-bfcm-heading { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
				.bplugins-bfcm-heading > strong { font-size: 16px; line-height: 1.4; }
				.bplugins-bfcm-badge {
					display: inline-block;
					padding: 5px 10px;
					border-radius: 20px;
					background: color-mix(in srgb, var(--bfcm-accent) 15%, white);
					color: var(--bfcm-accent);
					font-size: 12px;
					font-weight: 700;
				}
				.bplugins-bfcm-content p { margin: 5px 0 7px; color: #526078; font-size: 13px; }
				.bplugins-bfcm-features {
					display: flex;
					flex-wrap: wrap;
					gap: 5px 16px;
					margin: 0;
					padding: 0;
					list-style: none;
				}
				.bplugins-bfcm-features li { color: #526078; font-size: 13px; }
				.bplugins-bfcm-features li:before { content: "✓"; margin-right: 5px; color: #16834a; font-weight: 800; }
				.bplugins-bfcm-button {
					flex: 0 0 auto;
					display: inline-flex;
					align-items: center;
					gap: 8px;
					padding: 12px 18px;
					border-radius: 6px;
					background: <?php echo esc_html( $this->config['primary_color'] ); ?>;
					color: #fff !important;
					font-size: 13px;
					font-weight: 700;
					text-decoration: none;
					box-shadow: 0 2px 5px rgba(29,78,216,.2);
				}
				.bplugins-bfcm-button:hover { filter: brightness(.94); color: #fff; }
				.bplugins-bfcm-dismiss {
					position: absolute;
					top: 8px;
					right: 9px;
					border: 0;
					background: transparent;
					color: #8792a5;
					font-size: 23px;
					line-height: 1;
					cursor: pointer;
				}
				.bplugins-bfcm-dismiss:hover { color: #1f2937; }
		
				@media (max-width: 900px) {
					.bplugins-bfcm-banner { flex-wrap: wrap; gap: 12px; }
					.bplugins-bfcm-brand { flex-basis: 110px; }
					.bplugins-bfcm-button { margin-left: auto; }
				}
				@media (max-width: 600px) {
					.bplugins-bfcm-banner { padding: 12px 38px 12px 12px; }
					.bplugins-bfcm-brand { flex-basis: 100%; min-height: 70px; margin: -12px -38px 0 -12px; border-radius: 9px 9px 0 0; }
					.bplugins-bfcm-button { width: 100%; justify-content: center; }
				}
			</style>
			<script>
				(function () {
					var banner = document.querySelector('.bplugins-bfcm-banner');
					if (!banner) return;
					var closeButton = banner.querySelector('.bplugins-bfcm-dismiss');
					if (!closeButton) return;

					closeButton.addEventListener('click', function () {
						closeButton.disabled = true;
						var body = new URLSearchParams();
						body.append('action', 'bplugins_bfcm2026_dismiss');
						body.append('_ajax_nonce', '<?php echo esc_js( wp_create_nonce( 'bplugins_bfcm2026_dismiss' ) ); ?>');

						fetch(ajaxurl, {
							method: 'POST',
							credentials: 'same-origin',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
							body: body.toString()
						}).then(function (response) {
							if (!response.ok) throw new Error('Request failed');
							banner.remove();
						}).catch(function () {
							closeButton.disabled = false;
						});
					});
				})();
			</script>
			<?php
		}

		/**
		 * Store dismissal for the current WordPress user.
		 */
		public function ajax_dismiss() {
			check_ajax_referer( 'bplugins_bfcm2026_dismiss' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
			}

			update_user_meta( get_current_user_id(), $this->dismiss_meta_key, 1 );
			wp_send_json_success();
		}
	}
}

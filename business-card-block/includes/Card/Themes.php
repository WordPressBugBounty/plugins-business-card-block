<?php
/**
 * Theme registry and Pro gate.
 *
 * Single source of truth for which card designs are premium. The gate is
 * enforced here, on the server, before a theme name is ever handed to the
 * browser — so a free install cannot reveal a Pro design by deleting an
 * overlay in devtools.
 *
 * @package BusinessCardBlock
 */

namespace BCB\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Themes {

	/**
	 * Design used when a premium theme is not licensed.
	 */
	const FALLBACK = 'default';

	/**
	 * Every design the core block ships, in inserter order.
	 *
	 * @return array slug => is_pro
	 */
	public static function all() {
		return array(
			'default'    => false,
			'minimal_qr' => false,
			'theme1'     => false,
			'theme2'     => false,
			'theme3'     => false,
			'theme4'     => true,
			'theme5'     => true,
			'theme6'     => true,
			'theme7'     => true,
			'theme8'     => true,
		);
	}

	/**
	 * Premium theme slugs.
	 *
	 * @return string[]
	 */
	public static function pro() {
		return array_keys( array_filter( self::all() ) );
	}

	/**
	 * Whether a theme requires a licence.
	 *
	 * @param string $theme Theme slug.
	 * @return bool
	 */
	public static function is_pro( $theme ) {
		$all = self::all();

		return isset( $all[ $theme ] ) ? (bool) $all[ $theme ] : false;
	}

	/**
	 * Whether this install may use premium themes.
	 *
	 * @return bool
	 */
	public static function can_use_pro() {
		return function_exists( 'bcbIsPremium' ) ? (bool) bcbIsPremium() : false;
	}

	/**
	 * The theme that should actually render.
	 *
	 * A premium design on an unlicensed site degrades to the default layout
	 * rather than rendering behind a removable overlay. This is reached only by
	 * content saved before the editor started refusing Pro themes, or by a
	 * licence that has since lapsed.
	 *
	 * @param string $theme Requested theme slug.
	 * @return string Theme slug that may be rendered.
	 */
	public static function resolve( $theme ) {
		$theme = is_scalar( $theme ) ? (string) $theme : '';

		if ( '' === $theme ) {
			return self::FALLBACK;
		}

		if ( self::is_pro( $theme ) && ! self::can_use_pro() ) {
			return self::FALLBACK;
		}

		return $theme;
	}

	/**
	 * Theme options for the editor, carrying the Pro flag.
	 *
	 * The editor reads this rather than keeping its own copy of the Pro list.
	 *
	 * @return array[]
	 */
	public static function js_table() {
		$out = array();

		foreach ( self::all() as $slug => $is_pro ) {
			$out[ $slug ] = array(
				'isPro' => (bool) $is_pro,
			);
		}

		return $out;
	}
}

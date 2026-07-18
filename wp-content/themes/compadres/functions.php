<?php
/**
 * Compadres theme setup.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'compadres', get_template_directory() . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 96,
				'width'       => 320,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		register_nav_menus(
			array(
				'primary' => __( 'Primary navigation', 'compadres' ),
				'footer'  => __( 'Footer navigation', 'compadres' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$version = wp_get_theme()->get( 'Version' );
		wp_enqueue_style( 'compadres-main', get_template_directory_uri() . '/assets/css/main.css', array(), $version );
		wp_enqueue_script( 'compadres-navigation', get_template_directory_uri() . '/assets/js/navigation.js', array(), $version, true );
		$accent = sanitize_hex_color( get_theme_mod( 'compadres_accent_color', '#d6ad68' ) );
		if ( $accent ) {
			wp_add_inline_style( 'compadres-main', ':root{--color-brass:' . $accent . ';}' );
		}
	}
);

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'compadres-site';
		return $classes;
	}
);

/**
 * Register native Customizer settings for approved storefront content.
 *
 * @param WP_Customize_Manager $manager Customizer manager.
 */
function compadres_customize_register( WP_Customize_Manager $manager ): void {
	$manager->add_section(
		'compadres_storefront',
		array(
			'title'       => __( 'Compadres Storefront', 'compadres' ),
			'description' => __( 'Configure approved storefront copy and contact links. Site Icon and Logo remain in Site Identity.', 'compadres' ),
			'priority'    => 30,
		)
	);

	$text_settings = array(
		'compadres_hero_heading'       => array( __( 'Hero heading', 'compadres' ), 'A shared table. A considered smoke.' ),
		'compadres_hero_description'   => array( __( 'Hero description', 'compadres' ), 'Explore a unified collection of premium cigar brands.' ),
		'compadres_cta_label'          => array( __( 'Primary call-to-action label', 'compadres' ), 'Shop all cigars' ),
		'compadres_footer_description' => array( __( 'Footer description', 'compadres' ), 'Premium cigar storefront — approved brand content pending.' ),
		'compadres_age_notice'         => array( __( 'Age notice', 'compadres' ), 'For adults 21 and older. Site entry confirmation does not replace checkout identity verification.' ),
		'compadres_shipping_notice'    => array( __( 'Shipping notice', 'compadres' ), 'Availability and shipping eligibility are confirmed during checkout.' ),
	);

	foreach ( $text_settings as $setting_id => $setting ) {
		$manager->add_setting(
			$setting_id,
			array(
				'default'           => $setting[1],
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$manager->add_control(
			$setting_id,
			array(
				'label'   => $setting[0],
				'section' => 'compadres_storefront',
				'type'    => str_contains( $setting_id, 'description' ) || str_contains( $setting_id, 'notice' ) ? 'textarea' : 'text',
			)
		);
	}

	$url_settings = array(
		'compadres_cta_url'   => array( __( 'Primary call-to-action URL', 'compadres' ), '/shop/' ),
		'compadres_instagram' => array( __( 'Instagram URL', 'compadres' ), '' ),
		'compadres_facebook'  => array( __( 'Facebook URL', 'compadres' ), '' ),
		'compadres_x'         => array( __( 'X URL', 'compadres' ), '' ),
	);
	foreach ( $url_settings as $setting_id => $setting ) {
		$manager->add_setting(
			$setting_id,
			array(
				'default'           => $setting[1],
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		$manager->add_control(
			$setting_id,
			array(
				'label'   => $setting[0],
				'section' => 'compadres_storefront',
				'type'    => 'url',
			)
		);
	}

	$manager->add_setting( 'compadres_support_email', array( 'sanitize_callback' => 'sanitize_email' ) );
	$manager->add_control(
		'compadres_support_email',
		array(
			'label'   => __( 'Support email', 'compadres' ),
			'section' => 'compadres_storefront',
			'type'    => 'email',
		)
	);
	$manager->add_setting( 'compadres_hero_image', array( 'sanitize_callback' => 'absint' ) );
	$manager->add_control(
		new WP_Customize_Media_Control(
			$manager,
			'compadres_hero_image',
			array(
				'label'     => __( 'Hero image', 'compadres' ),
				'section'   => 'compadres_storefront',
				'mime_type' => 'image',
			)
		)
	);
	$manager->add_setting(
		'compadres_accent_color',
		array(
			'default'           => '#d6ad68',
			'sanitize_callback' => 'sanitize_hex_color',
		)
	);
	$manager->add_control(
		new WP_Customize_Color_Control(
			$manager,
			'compadres_accent_color',
			array(
				'label'   => __( 'Accent color', 'compadres' ),
				'section' => 'compadres_storefront',
			)
		)
	);
}
add_action( 'customize_register', 'compadres_customize_register' );

/**
 * Determine whether a WooCommerce product query has results.
 *
 * @param array<string, mixed> $query Product query arguments.
 */
function compadres_has_products( array $query = array() ): bool {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return false;
	}
	$query['limit']  = 1;
	$query['return'] = 'ids';
	return ! empty( wc_get_products( $query ) );
}

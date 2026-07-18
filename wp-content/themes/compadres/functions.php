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
	}
);

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'compadres-site';
		return $classes;
	}
);

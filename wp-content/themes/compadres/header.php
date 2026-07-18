<?php
/** Theme header. */
defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main-content"><?php esc_html_e( 'Skip to content', 'compadres' ); ?></a>
<header class="site-header">
	<div class="site-header__inner">
		<a class="wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Compadres Cigars home', 'compadres' ); ?>">
			<span class="wordmark__crest" aria-hidden="true">C</span>
			<span>Compadres <small>Cigars</small></span>
		</a>
		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
			<span class="screen-reader-text"><?php esc_html_e( 'Toggle navigation', 'compadres' ); ?></span>
			<span aria-hidden="true">Menu</span>
		</button>
		<nav id="primary-navigation" class="primary-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'compadres' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'fallback_cb'    => 'compadres_default_menu',
				)
			);
			?>
		</nav>
		<a class="header-cart" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>"><?php esc_html_e( 'Cart', 'compadres' ); ?></a>
	</div>
</header>
<?php
function compadres_default_menu(): void {
	echo '<ul class="menu">';
	echo '<li><a href="' . esc_url( home_url( '/shop/' ) ) . '">' . esc_html__( 'Shop', 'compadres' ) . '</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/brands/' ) ) . '">' . esc_html__( 'Brands', 'compadres' ) . '</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/about/' ) ) . '">' . esc_html__( 'About', 'compadres' ) . '</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/my-account/' ) ) . '">' . esc_html__( 'My Account', 'compadres' ) . '</a></li>';
	echo '</ul>';
}

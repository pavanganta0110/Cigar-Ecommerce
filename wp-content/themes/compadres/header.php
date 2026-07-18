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
		<?php if ( has_custom_logo() ) : ?>
			<div class="wordmark wordmark--logo"><?php the_custom_logo(); ?></div>
		<?php else : ?>
			<a class="wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Compadres Cigars home', 'compadres' ); ?>">
				<span class="wordmark__crest" aria-hidden="true">C</span>
				<span>Compadres <small>Cigars</small></span>
			</a>
		<?php endif; ?>
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
	$items = array(
		array( '/shop/', __( 'Shop', 'compadres' ), function_exists( 'is_shop' ) && is_shop() ),
		array( '/brands/', __( 'Brands', 'compadres' ), is_tax( 'compadres_brand' ) ),
		array( '/about/', __( 'About', 'compadres' ), is_page( 'about' ) ),
		array( '/contact/', __( 'Contact', 'compadres' ), is_page( 'contact' ) ),
		array( '/my-account/', __( 'My Account', 'compadres' ), function_exists( 'is_account_page' ) && is_account_page() ),
	);
	echo '<ul class="menu">';
	foreach ( $items as $item ) {
		printf(
			'<li><a href="%1$s"%2$s>%3$s</a></li>',
			esc_url( home_url( $item[0] ) ),
			$item[2] ? ' aria-current="page"' : '',
			esc_html( $item[1] )
		);
	}
	echo '</ul>';
}

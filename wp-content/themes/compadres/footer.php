<?php
/** Theme footer. */
defined( 'ABSPATH' ) || exit;
?>
<footer class="site-footer">
	<div class="site-footer__grid">
		<div><strong>Compadres Cigars</strong><p><?php esc_html_e( 'Premium cigar storefront — development branding pending approved assets.', 'compadres' ); ?></p></div>
		<nav aria-label="<?php esc_attr_e( 'Policy navigation', 'compadres' ); ?>">
			<a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>"><?php esc_html_e( 'Shipping Policy', 'compadres' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/age-policy/' ) ); ?>"><?php esc_html_e( 'Age Policy', 'compadres' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'compadres' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( 'Terms', 'compadres' ); ?></a>
		</nav>
	</div>
	<p class="site-footer__notice"><?php esc_html_e( 'For adults 21 and older. Site entry confirmation does not replace checkout identity verification.', 'compadres' ); ?></p>
</footer>
<?php wp_footer(); ?>
</body>
</html>

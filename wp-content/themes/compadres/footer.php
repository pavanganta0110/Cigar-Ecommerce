<?php
/** Site footer. */
$support_email = sanitize_email( get_theme_mod( 'compadres_support_email', '' ) );
$social_links  = array_filter(
	array(
		'Instagram' => esc_url( get_theme_mod( 'compadres_instagram', '' ) ),
		'Facebook'  => esc_url( get_theme_mod( 'compadres_facebook', '' ) ),
		'X'         => esc_url( get_theme_mod( 'compadres_x', '' ) ),
	)
);
?>
<footer class="site-footer">
	<div class="site-footer__grid">
		<div><strong>Compadres Cigars</strong><p><?php echo esc_html( get_theme_mod( 'compadres_footer_description', 'Premium cigar storefront — approved brand content pending.' ) ); ?></p></div>
		<nav aria-label="<?php esc_attr_e( 'Policy navigation', 'compadres' ); ?>">
			<a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>"><?php esc_html_e( 'Shipping Policy', 'compadres' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/age-policy/' ) ); ?>"><?php esc_html_e( 'Age Policy', 'compadres' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'compadres' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/returns-policy/' ) ); ?>"><?php esc_html_e( 'Returns', 'compadres' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( 'Terms', 'compadres' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/restrictions/' ) ); ?>"><?php esc_html_e( 'Restrictions', 'compadres' ); ?></a>
		</nav>
		<?php if ( $support_email || $social_links ) : ?>
			<div class="site-footer__contact">
				<?php if ( $support_email ) : ?>
					<a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php echo esc_html( $support_email ); ?></a>
				<?php endif; ?>
				<?php foreach ( $social_links as $social_name => $social_url ) : ?>
					<a href="<?php echo esc_url( $social_url ); ?>" rel="noopener noreferrer"><?php echo esc_html( $social_name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<p class="site-footer__notice"><?php echo esc_html( get_theme_mod( 'compadres_age_notice', 'For adults 21 and older. Site entry confirmation does not replace checkout identity verification.' ) ); ?></p>
</footer>
<?php wp_footer(); ?>
</body>
</html>

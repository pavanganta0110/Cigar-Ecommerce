<?php
/**
 * Accessible error notices.
 *
 * @package Compadres
 * @version 8.6.0
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $notices ) ) {
	return;
}
?>
<div class="woocommerce-error" role="alert">
	<ul role="list">
		<?php foreach ( $notices as $notice ) : ?>
			<li<?php echo wc_get_notice_data_attr( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes notice attributes. ?>>
				<?php echo wc_kses_notice( $notice['notice'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce notice allowlist. ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>

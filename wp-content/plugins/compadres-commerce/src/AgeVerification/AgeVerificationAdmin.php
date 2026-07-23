<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use Compadres\Commerce\Audit\AuditServiceFactory;
use Compadres\Commerce\Audit\ChangedValues;
use DateTimeImmutable;
use DomainException;
use WC_Order;

final class AgeVerificationAdmin {

	public function registerHooks(): void {
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
		add_action( 'admin_menu', array( $this, 'registerSettingsPage' ) );
		add_action( 'add_option_' . WordPressAgeVerificationRuntime::OPTION, array( $this, 'auditSettingsCreated' ), 10, 2 );
		add_action( 'update_option_' . WordPressAgeVerificationRuntime::OPTION, array( $this, 'auditSettingsChange' ), 10, 2 );
		add_filter( 'option_page_capability_compadres_age_verification', array( $this, 'settingsCapability' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'renderManualControls' ) );
		add_action( 'admin_post_compadres_age_manual_decision', array( $this, 'handleManualDecision' ) );
		add_action( 'admin_notices', array( $this, 'renderDecisionNotice' ) );
	}

	public function settingsCapability(): string {
		return 'compadres_manage_compliance';
	}

	public function registerSettings(): void {
		register_setting(
			'compadres_age_verification',
			WordPressAgeVerificationRuntime::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => static fn ( mixed $value ): array => AgeVerificationSettings::sanitize( is_array( $value ) ? $value : array() ),
				'default'           => AgeVerificationSettings::defaults(),
			)
		);
	}

	public function registerSettingsPage(): void {
		add_submenu_page(
			'compadres-audit-log',
			__( 'Checkout Age Verification', 'compadres-commerce' ),
			__( 'Age Verification', 'compadres-commerce' ),
			'compadres_manage_compliance',
			'compadres-age-verification',
			array( $this, 'renderSettingsPage' )
		);
	}

	public function renderSettingsPage(): void {
		if ( ! current_user_can( 'compadres_manage_compliance' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage age-verification settings.', 'compadres-commerce' ) );
		}
		$value    = get_option( WordPressAgeVerificationRuntime::OPTION, AgeVerificationSettings::defaults() );
		$settings = AgeVerificationSettings::sanitize( is_array( $value ) ? $value : array() );
		$status   = ProviderConfiguration::fromArray( $settings )->integrationStatus();
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Checkout Age Verification', 'compadres-commerce' ); ?></h1>
			<?php settings_errors(); ?>
			<p><strong><?php esc_html_e( 'Integration status:', 'compadres-commerce' ); ?></strong> <?php echo esc_html( 'not_configured' === $status ? __( 'Not configured', 'compadres-commerce' ) : $status ); ?></p>
			<p><?php esc_html_e( 'The site-entry age gate is not checkout verification. Checkout remains blocked unless the authoritative server-side result is an unexpired pass.', 'compadres-commerce' ); ?></p>
			<form action="options.php" method="post">
				<?php settings_fields( 'compadres_age_verification' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Checkout verification', 'compadres-commerce' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( WordPressAgeVerificationRuntime::OPTION ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Require checkout age verification', 'compadres-commerce' ); ?></label><p class="description"><?php esc_html_e( 'Disabling this only bypasses verification in a development environment.', 'compadres-commerce' ); ?></p></td></tr>
					<tr><th scope="row"><label for="compadres-age-provider"><?php esc_html_e( 'Provider', 'compadres-commerce' ); ?></label></th><td><select id="compadres-age-provider" name="<?php echo esc_attr( WordPressAgeVerificationRuntime::OPTION ); ?>[provider]"><option value=""><?php esc_html_e( 'Not configured', 'compadres-commerce' ); ?></option><option value="agechecker" <?php selected( $settings['provider'], 'agechecker' ); ?>>AgeChecker</option><option value="mock" <?php selected( $settings['provider'], 'mock' ); ?>><?php esc_html_e( 'Development mock', 'compadres-commerce' ); ?></option></select></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Date of birth', 'compadres-commerce' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( WordPressAgeVerificationRuntime::OPTION ); ?>[requires_date_of_birth]" value="1" <?php checked( $settings['requires_date_of_birth'] ); ?>> <?php esc_html_e( 'Collect DOB transiently only when required by the approved provider configuration', 'compadres-commerce' ); ?></label></td></tr>
					<tr><th scope="row"><label for="compadres-hosted-template"><?php esc_html_e( 'Hosted workflow URL template', 'compadres-commerce' ); ?></label></th><td><input class="large-text code" id="compadres-hosted-template" name="<?php echo esc_attr( WordPressAgeVerificationRuntime::OPTION ); ?>[hosted_url_template]" value="<?php echo esc_attr( (string) $settings['hosted_url_template'] ); ?>"><p class="description"><?php esc_html_e( 'Must be an approved HTTPS AgeChecker URL containing {reference} and {return_url}.', 'compadres-commerce' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Production approval', 'compadres-commerce' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( WordPressAgeVerificationRuntime::OPTION ); ?>[production_approved]" value="1" <?php checked( $settings['production_approved'] ); ?>> <?php esc_html_e( 'Written production approval has been confirmed', 'compadres-commerce' ); ?></label><p class="description"><?php esc_html_e( 'Credentials alone do not establish production approval.', 'compadres-commerce' ); ?></p></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function renderManualControls( WC_Order $order ): void {
		if ( ! current_user_can( 'compadres_review_age_verification' ) || VerificationStatus::MANUAL_REVIEW !== $order->get_meta( '_compadres_age_status', true ) || '' !== (string) $order->get_meta( '_compadres_age_manual_action', true ) ) {
			return;
		}
		?>
		<div class="order_data_column" id="compadres-age-manual-decision"><h4><?php esc_html_e( 'Age-verification manual decision', 'compadres-commerce' ); ?></h4><p><?php esc_html_e( 'This is a reference-only compliance decision. Compadres does not receive or review identity documents.', 'compadres-commerce' ); ?></p>
			<input type="hidden" name="action" value="compadres_age_manual_decision"><input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>"><?php wp_nonce_field( 'compadres_age_manual_decision_' . $order->get_id(), 'compadres_age_nonce' ); ?>
			<label for="compadres-manual-reason"><?php esc_html_e( 'Optional non-sensitive reason', 'compadres-commerce' ); ?></label><input id="compadres-manual-reason" name="reason" maxlength="100" type="text">
			<button class="button" name="decision" value="approved" type="submit" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" formmethod="post"><?php esc_html_e( 'Approve age verification', 'compadres-commerce' ); ?></button> <button class="button" name="decision" value="rejected" type="submit" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" formmethod="post"><?php esc_html_e( 'Reject age verification', 'compadres-commerce' ); ?></button>
		</div>
		<?php
	}

	public function handleManualDecision(): void {
		if ( ! current_user_can( 'compadres_review_age_verification' ) ) {
			wp_die( esc_html__( 'You are not authorized to make age-verification decisions.', 'compadres-commerce' ), '', array( 'response' => 403 ) );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		check_admin_referer( 'compadres_age_manual_decision_' . $order_id, 'compadres_age_nonce' );
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'compadres-commerce' ), '', array( 'response' => 404 ) );
		}
		$lock       = 'compadres_age_decision_lock_' . $order_id;
		$lock_value = wp_generate_uuid4() . '|' . time();
		if ( ! $this->acquireDecisionLock( $lock, $lock_value ) ) {
			wp_die( esc_html__( 'An age-verification decision is already in progress.', 'compadres-commerce' ), '', array( 'response' => 409 ) );
		}
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$reason   = isset( $_POST['reason'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['reason'] ) ), 0, 100 ) : '';
		$error    = null;
		try {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				throw new DomainException( 'Order not found.' );
			}
			( new ManualDecisionService() )->decide( new WooCommerceOrderMetaWriter( $order ), $decision, get_current_user_id(), new DateTimeImmutable( 'now', wp_timezone() ) );
			$order->save();
		} catch ( DomainException $exception ) {
			$error = $exception;
		} finally {
			$this->releaseDecisionLock( $lock, $lock_value );
		}
		if ( $error instanceof DomainException ) {
			wp_die( esc_html( $error->getMessage() ), '', array( 'response' => 409 ) );
		}
		$event = 'approved' === $decision ? 'age_verification.manual_approved' : 'age_verification.manual_rejected';
		AuditServiceFactory::create()->entityChange(
			$event,
			'order',
			(string) $order_id,
			array( 'status' => VerificationStatus::MANUAL_REVIEW ),
			array(
				'status'      => $order->get_meta( '_compadres_age_status', true ),
				'reason'      => $reason,
				'reviewer_id' => get_current_user_id(),
			),
			get_current_user_id(),
			AuditServiceFactory::requestContext()
		);
		$redirect_url = add_query_arg(
			array(
				'compadres_age_decision'     => $decision,
				'compadres_age_notice_nonce' => wp_create_nonce( 'compadres_age_decision_notice' ),
			),
			$order->get_edit_order_url()
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function renderDecisionNotice(): void {
		if ( ! current_user_can( 'compadres_review_age_verification' ) || ! isset( $_GET['compadres_age_decision'], $_GET['compadres_age_notice_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_GET['compadres_age_notice_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'compadres_age_decision_notice' ) ) {
			return;
		}
		$decision = sanitize_key( wp_unslash( $_GET['compadres_age_decision'] ) );
		if ( ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html( 'approved' === $decision ? __( 'Age verification approved.', 'compadres-commerce' ) : __( 'Age verification rejected.', 'compadres-commerce' ) );
		echo '</p></div>';
	}

	/** @phpstan-impure */
	private function acquireDecisionLock( string $lock, string $value ): bool {
		if ( add_option( $lock, $value, '', false ) ) {
			return true;
		}
		$current = (string) get_option( $lock, '' );
		$parts   = explode( '|', $current );
		if ( (int) end( $parts ) >= time() - 300 ) {
			return false;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic compare-and-swap prevents deleting another request's lock.
		$updated = $wpdb->update(
			$wpdb->options,
			array( 'option_value' => $value ),
			array(
				'option_name'  => $lock,
				'option_value' => $current,
			),
			array( '%s' ),
			array( '%s', '%s' )
		);
		wp_cache_delete( $lock, 'options' );
		return 1 === $updated;
	}

	private function releaseDecisionLock( string $lock, string $value ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Ownership-qualified delete cannot release another request's lock.
		$wpdb->delete(
			$wpdb->options,
			array(
				'option_name'  => $lock,
				'option_value' => $value,
			),
			array( '%s', '%s' )
		);
		wp_cache_delete( $lock, 'options' );
	}

	public function auditSettingsCreated( string $option, mixed $current ): void {
		if ( WordPressAgeVerificationRuntime::OPTION === $option ) {
			$this->auditSettingsChange( AgeVerificationSettings::defaults(), $current );
		}
	}

	public function auditSettingsChange( mixed $previous, mixed $current ): void {
		$changes = ChangedValues::between( is_array( $previous ) ? $previous : array(), is_array( $current ) ? $current : array() );
		if ( ! $changes['changed'] ) {
			return;
		}
		AuditServiceFactory::create()->entityChange( 'age_verification.settings_updated', 'settings', WordPressAgeVerificationRuntime::OPTION, $changes['previous'], $changes['current'], get_current_user_id(), AuditServiceFactory::requestContext() );
	}
}

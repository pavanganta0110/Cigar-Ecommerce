<?php

declare(strict_types=1);

namespace Compadres\Commerce\Compliance;

use Compadres\Commerce\Plugin;

final class AgeGate {

	public const OPTION = 'compadres_age_gate';
	public const COOKIE = 'compadres_age_confirmed';

	public function registerHooks(): void {
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
		add_action( 'admin_menu', array( $this, 'registerSettingsPage' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueAssets' ) );
		add_action( 'wp_footer', array( $this, 'render' ) );
		add_action( 'wp_ajax_compadres_age_confirm', array( $this, 'confirm' ) );
		add_action( 'wp_ajax_nopriv_compadres_age_confirm', array( $this, 'confirm' ) );
	}

	public function registerSettings(): void {
		register_setting(
			'compadres_compliance',
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => static fn ( mixed $value ): array => AgeGateSettings::sanitize( is_array( $value ) ? $value : array() ),
				'default'           => AgeGateSettings::defaults(),
			)
		);
	}

	public function registerSettingsPage(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Compadres Compliance', 'compadres-commerce' ),
			__( 'Compliance', 'compadres-commerce' ),
			'compadres_manage_restrictions',
			'compadres-compliance',
			array( $this, 'renderSettingsPage' )
		);
	}

	public function enqueueAssets(): void {
		if ( ! $this->shouldDisplay() ) {
			return;
		}
		$plugin_file = dirname( __DIR__, 2 ) . '/compadres-commerce.php';
		wp_enqueue_style( 'compadres-age-gate', plugins_url( 'assets/css/age-gate.css', $plugin_file ), array(), Plugin::VERSION );
		wp_enqueue_script( 'compadres-age-gate', plugins_url( 'assets/js/age-gate.js', $plugin_file ), array(), Plugin::VERSION, true );
		wp_localize_script(
			'compadres-age-gate',
			'compadresAgeGate',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'compadres_age_gate' ),
			)
		);
	}

	public function render(): void {
		if ( ! $this->shouldDisplay() ) {
			return;
		}
		$settings = $this->settings();
		?>
		<div class="compadres-age-gate" data-age-gate data-escape-message="<?php esc_attr_e( 'Please confirm your age or use the exit link.', 'compadres-commerce' ); ?>">
			<div class="compadres-age-gate__dialog" role="dialog" aria-modal="true" aria-labelledby="compadres-age-gate-title" aria-describedby="compadres-age-gate-description" tabindex="-1">
				<p class="compadres-age-gate__eyebrow"><?php esc_html_e( 'Compadres Cigars', 'compadres-commerce' ); ?></p>
				<h2 id="compadres-age-gate-title"><?php echo esc_html( $settings['title'] ); ?></h2>
				<p id="compadres-age-gate-description"><?php echo esc_html( $settings['explanatory_text'] ); ?></p>
				<div class="compadres-age-gate__actions">
					<button type="button" class="compadres-age-gate__confirm" data-age-confirm><?php echo esc_html( $settings['confirmation_label'] ); ?></button>
					<a class="compadres-age-gate__exit" data-age-exit href="<?php echo esc_url( $settings['exit_url'] ); ?>"><?php echo esc_html( $settings['exit_label'] ); ?></a>
				</div>
				<p class="compadres-age-gate__status" data-age-status role="status" aria-live="polite"></p>
			</div>
		</div>
		<?php
	}

	public function confirm(): void {
		check_ajax_referer( 'compadres_age_gate', 'nonce' );
		$settings = $this->settings();
		if ( ! $settings['enabled'] ) {
			wp_send_json_error( array( 'message' => __( 'The age gate is disabled.', 'compadres-commerce' ) ), 409 );
		}
		$now        = time();
		$expiration = $now + ( $settings['cookie_lifetime_hours'] * HOUR_IN_SECONDS );
		$same_site  = 'None' === $settings['same_site'] && ! is_ssl() ? 'Lax' : $settings['same_site'];
		$value      = $this->token()->issue( $now, $settings['cookie_lifetime_hours'] * HOUR_IN_SECONDS );
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expiration,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => $same_site,
			)
		);
		wp_send_json_success( array( 'expires' => $expiration ) );
	}

	public function hasConfirmation(): bool {
		$value = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return '' !== $value && $this->token()->isValid( $value, time() );
	}

	public function renderSettingsPage(): void {
		if ( ! current_user_can( 'compadres_manage_restrictions' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage compliance settings.', 'compadres-commerce' ) );
		}
		$settings = $this->settings();
		$fields   = array(
			'title'                 => __( 'Title', 'compadres-commerce' ),
			'explanatory_text'      => __( 'Explanatory text', 'compadres-commerce' ),
			'confirmation_label'    => __( 'Confirmation label', 'compadres-commerce' ),
			'exit_label'            => __( 'Exit label', 'compadres-commerce' ),
			'exit_url'              => __( 'Exit URL', 'compadres-commerce' ),
			'cookie_lifetime_hours' => __( 'Cookie lifetime (hours)', 'compadres-commerce' ),
		);
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Compadres Compliance', 'compadres-commerce' ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'compadres_compliance' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Age gate', 'compadres-commerce' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Enable the 21+ site-entry confirmation', 'compadres-commerce' ); ?></label></td></tr>
					<?php foreach ( $fields as $key => $label ) : ?>
						<tr><th scope="row"><label for="compadres-age-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="regular-text" id="compadres-age-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) $settings[ $key ] ); ?>" <?php echo 'cookie_lifetime_hours' === $key ? 'type="number" min="1" max="8760"' : 'type="text"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></td></tr>
					<?php endforeach; ?>
					<tr><th scope="row"><label for="compadres-age-same-site"><?php esc_html_e( 'SameSite', 'compadres-commerce' ); ?></label></th><td><select id="compadres-age-same-site" name="<?php echo esc_attr( self::OPTION ); ?>[same_site]">
					<?php
					foreach ( array( 'Lax', 'Strict', 'None' ) as $value ) :
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['same_site'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e( 'SameSite=None is downgraded to Lax when HTTPS is unavailable.', 'compadres-commerce' ); ?></p></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function shouldDisplay(): bool {
		$settings = $this->settings();
		return ! is_admin() && $settings['enabled'] && ! $this->hasConfirmation();
	}

	/** @return array{enabled:bool,title:string,explanatory_text:string,confirmation_label:string,exit_label:string,exit_url:string,cookie_lifetime_hours:int,same_site:string} */
	private function settings(): array {
		$value = get_option( self::OPTION, AgeGateSettings::defaults() );
		return AgeGateSettings::sanitize( is_array( $value ) ? $value : AgeGateSettings::defaults() );
	}

	private function token(): AgeGateToken {
		return new AgeGateToken( wp_salt( 'auth' ) );
	}
}

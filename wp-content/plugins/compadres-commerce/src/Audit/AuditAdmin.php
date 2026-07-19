<?php

declare(strict_types=1);

namespace Compadres\Commerce\Audit;

final class AuditAdmin {

	public function registerHooks(): void {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
	}

	public function registerPage(): void {
		add_menu_page(
			__( 'Compadres Audit Log', 'compadres-commerce' ),
			__( 'Audit Log', 'compadres-commerce' ),
			'compadres_view_audit_logs',
			'compadres-audit-log',
			array( $this, 'renderPage' ),
			'dashicons-shield-alt',
			58
		);
	}

	public function renderPage(): void {
		if ( ! current_user_can( 'compadres_view_audit_logs' ) ) {
			wp_die( esc_html__( 'You are not allowed to view audit logs.', 'compadres-commerce' ) );
		}
		global $wpdb;
		$store   = new WordPressAuditStore( $wpdb );
		$filters = $this->filters();
		$page    = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$rows    = $store->search( $filters, $page, 50 );
		$total   = $store->count( $filters );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Compadres Audit Log', 'compadres-commerce' ); ?></h1>
			<p><?php esc_html_e( 'Audit records are append-only operational records. Sensitive values are recursively redacted before storage.', 'compadres-commerce' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="compadres-audit-log">
				<?php
				foreach ( array(
					'date_from'   => 'Date from',
					'date_to'     => 'Date to',
					'event_type'  => 'Event type',
					'user_id'     => 'User ID',
					'entity_type' => 'Entity type',
					'entity_id'   => 'Entity ID',
					'result'      => 'Result',
				) as $name => $label ) :
					?>
					<label><?php echo esc_html( $label ); ?> <input name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) ( $filters[ $name ] ?? '' ) ); ?>"<?php echo str_starts_with( $name, 'date_' ) ? ' type="date"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute fragment. ?>></label>
				<?php endforeach; ?>
				<?php submit_button( __( 'Filter', 'compadres-commerce' ), 'secondary', 'filter_action', false ); ?>
			</form>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'ID', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Created', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Event', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'User', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Entity', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Result', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Details', 'compadres-commerce' ); ?></th></tr></thead>
				<tbody>
				<?php
				if ( ! $rows ) :
					?>
					<tr><td colspan="7"><?php esc_html_e( 'No audit records matched.', 'compadres-commerce' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $row['audit_id'] ); ?></td><td><?php echo esc_html( (string) $row['created_at'] ); ?></td><td><?php echo esc_html( (string) $row['event_type'] ); ?></td><td><?php echo esc_html( (string) $row['user_id'] ); ?></td><td><?php echo esc_html( trim( (string) $row['entity_type'] . ' ' . (string) $row['entity_id'] ) ); ?></td><td><?php echo esc_html( (string) $row['result'] ); ?></td>
						<td><details><summary><?php esc_html_e( 'View', 'compadres-commerce' ); ?></summary><p><strong><?php esc_html_e( 'Correlation ID:', 'compadres-commerce' ); ?></strong> <?php echo esc_html( (string) $row['correlation_id'] ); ?></p><p><strong><?php esc_html_e( 'Failure:', 'compadres-commerce' ); ?></strong> <?php echo esc_html( (string) $row['failure_reason'] ); ?></p><pre>
						<?php
						echo esc_html(
							(string) wp_json_encode(
								array(
									'previous' => $row['previous_value'],
									'new'      => $row['new_value'],
									'context'  => $row['request_context'],
								),
								JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
							)
						);
						?>
												</pre></details></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'current' => $page,
						'total'   => max( 1, (int) ceil( $total / 50 ) ),
					)
				)
			);
			?>
		</div>
		<?php
	}

	/** @return array<string, scalar> */
	private function filters(): array {
		$filters = array();
		foreach ( array( 'date_from', 'date_to', 'event_type', 'entity_type', 'entity_id', 'result' ) as $key ) {
			$value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
			if ( '' !== $value ) {
				$filters[ $key ] = $value;
			}
		}
		if ( isset( $_GET['user_id'] ) && '' !== $_GET['user_id'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
			$filters['user_id'] = absint( $_GET['user_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		}
		return $filters;
	}
}

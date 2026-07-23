<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use Compadres\Commerce\Audit\AuditServiceFactory;
use Compadres\Commerce\Catalog\BrandTaxonomy;
use DomainException;
use Throwable;

final class RestrictionAdmin {

	private const CAPABILITY = 'compadres_manage_compliance';
	private const PAGE       = 'compadres-restrictions';

	public function registerHooks(): void {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
		add_action( 'admin_post_compadres_save_restriction', array( $this, 'handleSave' ) );
		add_action( 'admin_post_compadres_toggle_restriction', array( $this, 'handleToggle' ) );
		add_action( 'admin_post_compadres_archive_restriction', array( $this, 'handleArchive' ) );
		add_filter( 'woocommerce_prevent_admin_access', array( $this, 'allowComplianceAdmin' ) );
	}

	public function allowComplianceAdmin( bool $prevent_access ): bool {
		return current_user_can( self::CAPABILITY ) ? false : $prevent_access;
	}

	public function registerPage(): void {
		add_menu_page(
			__( 'Geographic Restrictions', 'compadres-commerce' ),
			__( 'Restrictions', 'compadres-commerce' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'renderPage' ),
			'dashicons-location-alt',
			59
		);
	}

	public function renderPage(): void {
		$this->authorize();
		$repository = $this->repository();
		$rule_id    = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit selection.
		$editing    = $rule_id > 0 ? $repository->find( $rule_id ) : $this->defaults();
		$rules      = $repository->all();
		$this->renderNotice();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Geographic Checkout Restrictions', 'compadres-commerce' ); ?></h1>
			<p><?php esc_html_e( 'Rules are operational controls only. Every production rule requires independent legal review. Included development fixtures are fictional and are not legal guidance.', 'compadres-commerce' ); ?></p>
			<h2><?php esc_html_e( 'Rules', 'compadres-commerce' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Status', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Priority', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Country', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Effective dates', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Review source', 'compadres-commerce' ); ?></th><th><?php esc_html_e( 'Actions', 'compadres-commerce' ); ?></th></tr></thead><tbody>
			<?php if ( array() === $rules ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No restriction rules configured.', 'compadres-commerce' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rules as $rule ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $rule['name'] ); ?></td>
					<td><?php echo esc_html( $this->status( $rule ) ); ?></td>
					<td><?php echo esc_html( (string) $rule['priority'] ); ?></td>
					<td><?php echo esc_html( (string) $rule['country'] ); ?></td>
					<td><?php echo esc_html( (string) $rule['effective_at'] . ( empty( $rule['expires_at'] ) ? '' : ' — ' . (string) $rule['expires_at'] ) ); ?></td>
					<td><?php echo esc_html( (string) $rule['source_name'] . ( empty( $rule['review_date'] ) ? '' : ' (' . (string) $rule['review_date'] . ')' ) ); ?></td>
					<td>
						<?php if ( empty( $rule['archived_at'] ) ) : ?>
							<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'rule_id' => (int) $rule['id'] ), admin_url( 'admin.php' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?>"><?php esc_html_e( 'Edit', 'compadres-commerce' ); ?></a>
							<?php $this->renderActionForm( 'compadres_toggle_restriction', (int) $rule['id'], (int) $rule['revision'], ! empty( $rule['enabled'] ) ? 'deactivate' : 'activate', ! empty( $rule['enabled'] ) ? __( 'Deactivate', 'compadres-commerce' ) : __( 'Activate', 'compadres-commerce' ) ); ?>
							<?php $this->renderActionForm( 'compadres_archive_restriction', (int) $rule['id'], (int) $rule['revision'], 'archive', __( 'Archive', 'compadres-commerce' ) ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<h2><?php echo esc_html( $rule_id > 0 ? __( 'Edit restriction rule', 'compadres-commerce' ) : __( 'Add restriction rule', 'compadres-commerce' ) ); ?></h2>
			<?php $this->renderForm( $editing ); ?>
		</div>
		<?php
	}

	public function handleSave(): void {
		$this->authorize();
		$rule_id  = isset( $_POST['rule_id'] ) ? absint( wp_unslash( $_POST['rule_id'] ) ) : 0;
		$revision = isset( $_POST['revision'] ) ? absint( wp_unslash( $_POST['revision'] ) ) : 0;
		check_admin_referer( 'compadres_save_restriction_' . $rule_id, 'compadres_restriction_nonce' );
		try {
			$input = RestrictionRuleInput::fromArray( $this->postedFields(), wp_timezone() );
			$this->validateReferences( $input );
			$repository = $this->repository();
			if ( $rule_id > 0 ) {
				$previous = $repository->find( $rule_id );
				$repository->update( $rule_id, $revision, $input );
				$current = $repository->find( $rule_id );
				$this->audit( 'restriction.rule_updated', $rule_id, $previous, $current );
				if ( (int) $previous['enabled'] !== (int) $current['enabled'] ) {
					$this->audit( ! empty( $current['enabled'] ) ? 'restriction.rule_activated' : 'restriction.rule_deactivated', $rule_id, $previous, $current );
				}
			} else {
				$rule_id = $repository->create( $input );
				$current = $repository->find( $rule_id );
				$this->audit( 'restriction.rule_created', $rule_id, array(), $current );
			}
			$this->redirect( 'saved' );
		} catch ( DomainException ) {
			$this->redirect( 'invalid', $rule_id );
		}
	}

	public function handleToggle(): void {
		$this->authorize();
		$rule_id  = isset( $_POST['rule_id'] ) ? absint( wp_unslash( $_POST['rule_id'] ) ) : 0;
		$revision = isset( $_POST['revision'] ) ? absint( wp_unslash( $_POST['revision'] ) ) : 0;
		$mode     = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		check_admin_referer( 'compadres_toggle_restriction_' . $rule_id, 'compadres_restriction_nonce' );
		if ( ! in_array( $mode, array( 'activate', 'deactivate' ), true ) ) {
			wp_die( esc_html__( 'Restriction action is invalid.', 'compadres-commerce' ), '', array( 'response' => 400 ) );
		}
		$repository = $this->repository();
		try {
			$previous = $repository->find( $rule_id );
			$enabled  = 'activate' === $mode;
			$repository->setEnabled( $rule_id, $revision, $enabled );
			$current = $repository->find( $rule_id );
			$this->audit( $enabled ? 'restriction.rule_activated' : 'restriction.rule_deactivated', $rule_id, $previous, $current );
			$this->redirect( $enabled ? 'activated' : 'deactivated' );
		} catch ( DomainException ) {
			$this->redirect( 'conflict' );
		}
	}

	public function handleArchive(): void {
		$this->authorize();
		$rule_id  = isset( $_POST['rule_id'] ) ? absint( wp_unslash( $_POST['rule_id'] ) ) : 0;
		$revision = isset( $_POST['revision'] ) ? absint( wp_unslash( $_POST['revision'] ) ) : 0;
		check_admin_referer( 'compadres_archive_restriction_' . $rule_id, 'compadres_restriction_nonce' );
		$repository = $this->repository();
		try {
			$previous = $repository->find( $rule_id );
			$repository->archive( $rule_id, $revision );
			$current = $repository->find( $rule_id );
			$this->audit( 'restriction.rule_archived', $rule_id, $previous, $current );
			$this->redirect( 'archived' );
		} catch ( DomainException ) {
			$this->redirect( 'conflict' );
		}
	}

	/** @param array<string, mixed> $rule */
	private function renderForm( array $rule ): void {
		$targets = is_array( $rule['targets'] ?? null ) ? $rule['targets'] : array();
		$rule_id = (int) ( $rule['id'] ?? 0 );
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="compadres_save_restriction"><input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $rule_id ); ?>"><input type="hidden" name="revision" value="<?php echo esc_attr( (string) ( $rule['revision'] ?? 0 ) ); ?>">
			<?php wp_nonce_field( 'compadres_save_restriction_' . $rule_id, 'compadres_restriction_nonce' ); ?>
			<table class="form-table" role="presentation">
				<?php $this->textRow( 'name', __( 'Name', 'compadres-commerce' ), (string) ( $rule['name'] ?? '' ), true ); ?>
				<tr><th scope="row"><?php esc_html_e( 'Active status', 'compadres-commerce' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>> <?php esc_html_e( 'Active', 'compadres-commerce' ); ?></label></td></tr>
				<?php $this->textRow( 'priority', __( 'Priority', 'compadres-commerce' ), (string) ( $rule['priority'] ?? 0 ), true, 'number' ); ?>
				<?php $this->textRow( 'country', __( 'Country', 'compadres-commerce' ), 'US', true, 'text', true ); ?>
				<?php $this->textRow( 'state', __( 'State codes', 'compadres-commerce' ), $this->targetText( $targets, 'state' ) ); ?>
				<?php $this->textRow( 'city', __( 'Cities', 'compadres-commerce' ), $this->targetText( $targets, 'city' ) ); ?>
				<?php $this->textRow( 'postal_code', __( 'Exact postal codes', 'compadres-commerce' ), $this->targetText( $targets, 'postal_code' ) ); ?>
				<?php $this->textRow( 'postal_prefix', __( 'Explicit postal prefixes', 'compadres-commerce' ), $this->targetText( $targets, 'postal_prefix' ) ); ?>
				<?php $this->textRow( 'product_id', __( 'Product IDs', 'compadres-commerce' ), $this->targetText( $targets, 'product_id' ) ); ?>
				<?php $this->textRow( 'category_id', __( 'Product category IDs', 'compadres-commerce' ), $this->targetText( $targets, 'category_id' ) ); ?>
				<?php $this->textRow( 'brand_id', __( 'Brand IDs', 'compadres-commerce' ), $this->targetText( $targets, 'brand_id' ) ); ?>
				<?php $this->textRow( 'effective_at', __( 'Effective date and time', 'compadres-commerce' ), WordPressRestrictionAdminRepository::localDateTime( $rule['effective_at'] ?? '', wp_timezone() ), true, 'datetime-local' ); ?>
				<?php $this->textRow( 'expires_at', __( 'Expiration date and time', 'compadres-commerce' ), WordPressRestrictionAdminRepository::localDateTime( $rule['expires_at'] ?? '', wp_timezone() ), false, 'datetime-local' ); ?>
				<?php $this->textRow( 'blocked_message', __( 'Customer-facing blocked message', 'compadres-commerce' ), (string) ( $rule['blocked_message'] ?? '' ), true ); ?>
				<?php $this->textareaRow( 'notes', __( 'Internal note', 'compadres-commerce' ), (string) ( $rule['notes'] ?? '' ) ); ?>
				<?php $this->textRow( 'source_name', __( 'Source name', 'compadres-commerce' ), (string) ( $rule['source_name'] ?? '' ) ); ?>
				<?php $this->textRow( 'source_url', __( 'Source URL', 'compadres-commerce' ), (string) ( $rule['source_url'] ?? '' ), false, 'url' ); ?>
				<?php $this->textRow( 'review_date', __( 'Last reviewed date', 'compadres-commerce' ), (string) ( $rule['review_date'] ?? '' ), false, 'date' ); ?>
			</table>
			<p class="description"><?php esc_html_e( 'Separate multiple scope values with commas. Exact postal codes and explicit prefixes are different scopes. At least one destination or cart scope is required.', 'compadres-commerce' ); ?></p>
			<?php submit_button( $rule_id > 0 ? __( 'Update rule', 'compadres-commerce' ) : __( 'Add rule', 'compadres-commerce' ) ); ?>
		</form>
		<?php
	}

	private function textRow( string $name, string $label, string $value, bool $required = false, string $type = 'text', bool $read_only = false ): void {
		?>
		<tr><th scope="row"><label for="compadres-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="regular-text" id="compadres-<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $required ? ' required' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed boolean attribute. ?><?php echo $read_only ? ' readonly' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed boolean attribute. ?>></td></tr>
		<?php
	}

	private function textareaRow( string $name, string $label, string $value ): void {
		?>
		<tr><th scope="row"><label for="compadres-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th><td><textarea class="large-text" id="compadres-<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="3"><?php echo esc_textarea( $value ); ?></textarea></td></tr>
		<?php
	}

	private function renderActionForm( string $action, int $rule_id, int $revision, string $mode, string $label ): void {
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline"><input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>"><input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $rule_id ); ?>"><input type="hidden" name="revision" value="<?php echo esc_attr( (string) $revision ); ?>"><input type="hidden" name="mode" value="<?php echo esc_attr( $mode ); ?>"><?php wp_nonce_field( $action . '_' . $rule_id, 'compadres_restriction_nonce' ); ?><button class="button" type="submit"><?php echo esc_html( $label ); ?></button></form>
		<?php
	}

	/** @return array<string, mixed> */
	private function postedFields(): array {
		$fields = array( 'name', 'enabled', 'priority', 'country', 'state', 'city', 'postal_code', 'postal_prefix', 'product_id', 'category_id', 'brand_id', 'effective_at', 'expires_at', 'blocked_message', 'notes', 'source_name', 'source_url', 'review_date' );
		$result = array();
		foreach ( $fields as $field ) {
			$result[ $field ] = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Calling action verifies its rule-specific nonce before reading fields.
		}
		return $result;
	}

	private function validateReferences( RestrictionRuleInput $input ): void {
		$targets = $input->targets();
		foreach ( $targets['product_id'] as $id ) {
			if ( ! wc_get_product( (int) $id ) ) {
				throw new DomainException( 'Referenced product does not exist.' );
			}
		}
		foreach ( array(
			'category_id' => 'product_cat',
			'brand_id'    => BrandTaxonomy::TAXONOMY,
		) as $type => $taxonomy ) {
			foreach ( $targets[ $type ] as $id ) {
				if ( ! term_exists( (int) $id, $taxonomy ) ) {
					throw new DomainException( 'Referenced taxonomy term does not exist.' );
				}
			}
		}
	}

	private function authorize(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage geographic restrictions.', 'compadres-commerce' ), '', array( 'response' => 403 ) );
		}
	}

	private function repository(): WordPressRestrictionAdminRepository {
		global $wpdb;
		return new WordPressRestrictionAdminRepository( $wpdb );
	}

	/**
	 * @param array<string, mixed> $previous
	 * @param array<string, mixed> $current
	 */
	private function audit( string $event, int $rule_id, array $previous, array $current ): void {
		AuditServiceFactory::create()->entityChange( $event, 'restriction_rule', (string) $rule_id, $this->auditValue( $previous ), $this->auditValue( $current ), get_current_user_id(), AuditServiceFactory::requestContext() );
	}

	/**
	 * @param array<string, mixed> $value
	 * @return array<string, mixed>
	 */
	private function auditValue( array $value ): array {
		return array_intersect_key( $value, array_flip( array( 'id', 'name', 'enabled', 'priority', 'country', 'effective_at', 'expires_at', 'review_date', 'revision', 'archived_at' ) ) );
	}

	private function redirect( string $notice, int $rule_id = 0 ): never {
		$args = array(
			'page'                               => self::PAGE,
			'compadres_restriction_notice'       => $notice,
			'compadres_restriction_notice_nonce' => wp_create_nonce( 'compadres_restriction_notice' ),
		);
		if ( $rule_id > 0 ) {
			$args['rule_id'] = $rule_id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private function renderNotice(): void {
		if ( ! isset( $_GET['compadres_restriction_notice'], $_GET['compadres_restriction_notice_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_GET['compadres_restriction_notice_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'compadres_restriction_notice' ) ) {
			return;
		}
		$notice   = sanitize_key( wp_unslash( $_GET['compadres_restriction_notice'] ) );
		$messages = array(
			'saved'       => __( 'Restriction rule saved.', 'compadres-commerce' ),
			'activated'   => __( 'Restriction rule activated.', 'compadres-commerce' ),
			'deactivated' => __( 'Restriction rule deactivated.', 'compadres-commerce' ),
			'archived'    => __( 'Restriction rule archived to preserve historical references.', 'compadres-commerce' ),
			'invalid'     => __( 'Restriction rule could not be saved. Check every field and referenced product or term.', 'compadres-commerce' ),
			'conflict'    => __( 'Restriction rule changed in another request. Reload and try again.', 'compadres-commerce' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice notice-' . esc_attr( in_array( $notice, array( 'invalid', 'conflict' ), true ) ? 'error' : 'success' ) . '"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}

	/** @return array<string, mixed> */
	private function defaults(): array {
		return array(
			'id'              => 0,
			'revision'        => 0,
			'enabled'         => 1,
			'priority'        => 0,
			'country'         => 'US',
			'effective_at'    => current_time( 'Y-m-d\TH:i' ),
			'blocked_message' => 'We cannot ship the current cart to this destination. Please update the shipping address or remove the restricted item.',
			'targets'         => array(),
		);
	}

	/** @param array<string, mixed> $rule */
	private function status( array $rule ): string {
		if ( ! empty( $rule['archived_at'] ) ) {
			return __( 'Archived', 'compadres-commerce' );
		}
		return ! empty( $rule['enabled'] ) ? __( 'Active', 'compadres-commerce' ) : __( 'Inactive', 'compadres-commerce' );
	}

	/** @param array<string, mixed> $targets */
	private function targetText( array $targets, string $type ): string {
		$values = $targets[ $type ] ?? array();
		return is_array( $values ) ? implode( ', ', array_map( 'strval', $values ) ) : '';
	}
}

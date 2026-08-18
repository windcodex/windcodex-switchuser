<?php
/**
 * Admin notices — Pro upsell banner and review request.
 *
 * @package SwitchUser_Free
 */

defined( 'ABSPATH' ) || exit;

class SwitchUser_Notices {

	const REVIEW_DISMISSED_OPTION = 'switchuser_review_dismissed';
	const REVIEW_REMIND_TRANSIENT = 'switchuser_review_remind_later';
	const REVIEW_DAYS             = 7;
	const REVIEW_REMIND_DAYS      = 14;

	// ── Helpers ────────────────────────────────────────────────────────────

	private function on_settings_page(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'toplevel_page_switchuser-settings' === $screen->id;
	}

	// ── Render ─────────────────────────────────────────────────────────────

	public function render(): void {
		if ( ! $this->on_settings_page() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->render_review_notice();
	}

	public function render_pro_notice(): void {
		?>
		<div class="sg-pro-notice">
			<p class="sg-pro-notice-text">
				🚀 <strong><?php esc_html_e( 'SwitchUser Pro', 'windcodex-switchuser' ); ?></strong>
				<?php esc_html_e( '— IP allowlists, locked accounts, idle timeout, scheduled switching windows, re-authentication, email alerts, per-switch notifications, and full audit dashboard.', 'windcodex-switchuser' ); ?>
			</p>
			<a href="https://windcodex.com/product/woocommerce-user-switching-plugin/" target="_blank" rel="noopener" class="sg-pro-notice-cta">
				<?php esc_html_e( 'Explore SwitchUser Pro →', 'windcodex-switchuser' ); ?>
			</a>
		</div>
		<?php
	}

	public function render_review_notice(): void {
		if ( get_option( self::REVIEW_DISMISSED_OPTION, '' ) ) {
			return;
		}

		if ( get_transient( self::REVIEW_REMIND_TRANSIENT ) ) {
			return;
		}

		$activated = (int) get_option( 'switchuser_activated_time', 0 );

		if ( ! $activated ) {
			// Seed the time now for existing installs that pre-date this notice.
			update_option( 'switchuser_activated_time', time() );
			return;
		}

		if ( ( time() - $activated ) < ( self::REVIEW_DAYS * DAY_IN_SECONDS ) ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible sg-review-notice" id="sg-review-notice">
			<p class="sg-review-notice-title">
				<?php esc_html_e( '⭐ Is SwitchUser making account switching easier?', 'windcodex-switchuser' ); ?>
			</p>
			<p class="sg-review-notice-body">
				<?php esc_html_e( "You've been using SwitchUser for 7 days — we hope it's saving you time when switching between customer accounts.", 'windcodex-switchuser' ); ?>
				<br>
				<?php esc_html_e( "If it's been helpful, a quick review on WordPress.org takes less than 2 minutes and helps thousands of other store owners find the plugin.", 'windcodex-switchuser' ); ?>
			</p>
			<div class="sg-review-notice-actions">
				<a href="https://wordpress.org/support/plugin/windcodex-switchuser/reviews/#new-post"
				   target="_blank" rel="noopener"
				   class="sg-review-btn sg-review-btn-primary"
				   data-sg-review-action="reviewed">
					<?php esc_html_e( '⭐ Leave a Review', 'windcodex-switchuser' ); ?>
				</a>
				<button type="button" class="sg-review-btn sg-review-btn-secondary" data-sg-review-action="reviewed">
					<?php esc_html_e( 'I already did ✓', 'windcodex-switchuser' ); ?>
				</button>
				<button type="button" class="sg-review-btn sg-review-btn-link" data-sg-review-action="later">
					<?php esc_html_e( 'Maybe Later', 'windcodex-switchuser' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	// ── AJAX: dismiss review notice ────────────────────────────────────────

	public function ajax_dismiss(): void {
		check_ajax_referer( 'switchuser_dismiss_review', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'windcodex-switchuser' ) ), 403 );
		}

		$action = sanitize_key( $_POST['dismiss_action'] ?? 'later' );

		if ( 'later' === $action ) {
			set_transient( self::REVIEW_REMIND_TRANSIENT, 1, self::REVIEW_REMIND_DAYS * DAY_IN_SECONDS );
		} else {
			update_option( self::REVIEW_DISMISSED_OPTION, $action );
		}

		wp_send_json_success();
	}

	// ── Enqueue: add dismiss nonce to existing admin script ────────────────

	public function localize_nonce( string $hook ): void {
		if ( 'toplevel_page_switchuser-settings' !== $hook ) {
			return;
		}
		wp_localize_script( 'switchuser-admin-page', 'switchuser_notices', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'switchuser_dismiss_review' ),
		) );
	}
}

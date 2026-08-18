<?php
/**
 * SwitchUser free settings page.
 *
 * @package SwitchUser_Free
 */

defined( 'ABSPATH' ) || exit;

// Prepare role data for the Allowed Switcher Roles row.
$switchuser_all_roles      = wp_roles()->roles;
$switchuser_switcher_csv   = (string) ( $settings['allowed_switcher_roles'] ?? '' );
$switchuser_switcher_roles = '' !== trim( $switchuser_switcher_csv )
	? array_filter( array_map( 'trim', explode( ',', $switchuser_switcher_csv ) ) )
	: array();
?>
<div class="sg-header-card">
	<div class="sg-header-card-inner">
		<div class="sg-breadcrumb">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=switchuser-settings' ) ); ?>"><?php esc_html_e( 'SwitchUser', 'windcodex-switchuser' ); ?></a>
			<span class="sg-breadcrumb-sep">/</span>
			<span id="sg-breadcrumb-current"><?php esc_html_e( 'General', 'windcodex-switchuser' ); ?></span>
		</div>
		<div class="sg-help-wrap">
			<button type="button" class="sg-help-btn" id="sg-help-btn" aria-expanded="false" aria-haspopup="true">
				<span class="dashicons dashicons-editor-help"></span>
				<?php esc_html_e( 'Help', 'windcodex-switchuser' ); ?>
			</button>
			<div class="sg-help-dropdown" id="sg-help-dropdown" hidden>
				<a href="https://docs.windcodex.com/docs/switchuser" target="_blank" rel="noopener" class="sg-help-item">
					<span class="sg-help-item-icon dashicons dashicons-media-document"></span>
					<?php esc_html_e( 'Documentation', 'windcodex-switchuser' ); ?>
				</a>
				<a href="https://wordpress.org/support/plugin/windcodex-switchuser/reviews/#new-post" target="_blank" rel="noopener" class="sg-help-item">
					<span class="sg-help-item-icon dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Submit a Review', 'windcodex-switchuser' ); ?>
				</a>
				<a href="https://windcodex.com/product/woocommerce-user-switching-plugin/" target="_blank" rel="noopener" class="sg-help-item">
					<span class="sg-help-item-icon dashicons dashicons-awards"></span>
					<?php esc_html_e( 'Upgrade to Pro', 'windcodex-switchuser' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>

<div class="wrap sg-wrap">
<?php do_action( 'switchuser_before_settings' ); ?>
	<div class="sg-tabs-nav" role="tablist">
		<button class="sg-tab-btn sg-tab-active" data-tab="general" data-breadcrumb="<?php esc_attr_e( 'General', 'windcodex-switchuser' ); ?>"><?php esc_html_e( 'General', 'windcodex-switchuser' ); ?></button>
	</div>

	<form id="sg-settings-form" method="post">

		<!-- ══════════════════════════════════════════════════════════
		     TAB: GENERAL
		     ══════════════════════════════════════════════════════════ -->
		<div class="sg-tab-panel sg-tab-panel-active" data-panel="general">

			<!-- ── Access Control ──────────────────────────────────── -->
			<div class="sg-card">
				<div class="sg-card-header">
					<div>
						<div class="sg-card-header-title"><?php esc_html_e( 'Access Control', 'windcodex-switchuser' ); ?></div>
						<div class="sg-card-header-sub"><?php esc_html_e( 'Control who can start switch sessions and which accounts can be switched into.', 'windcodex-switchuser' ); ?></div>
					</div>
				</div>

				<div class="sg-form-row">
					<div class="sg-row-label">
						<div class="sg-row-title"><?php esc_html_e( 'Enable User Switching', 'windcodex-switchuser' ); ?></div>
						<div class="sg-row-hint"><?php esc_html_e( 'Master switch. When disabled nobody can start an impersonation session, regardless of other settings.', 'windcodex-switchuser' ); ?></div>
					</div>
					<div class="sg-row-body">
						<label class="sg-toggle-wrap">
							<input type="checkbox" name="switchuser_settings[enable_user_switching]" value="yes" class="sg-toggle-checkbox" <?php checked( $settings['enable_user_switching'] ?? 'no', 'yes' ); ?>>
							<span class="sg-toggle-track sg-toggle-sm"><span class="sg-toggle-thumb"></span></span>
						</label>
					</div>
				</div>

				<div class="sg-form-row">
					<div class="sg-row-label">
						<div class="sg-row-title"><?php esc_html_e( 'Block Switching Into Administrators', 'windcodex-switchuser' ); ?></div>
						<div class="sg-row-hint"><?php esc_html_e( 'Hard block: no switch session can impersonate an administrator account. Equal-or-higher privilege targets are also denied automatically.', 'windcodex-switchuser' ); ?></div>
					</div>
					<div class="sg-row-body">
						<label class="sg-toggle-wrap">
							<input type="checkbox" name="switchuser_settings[block_admin_targets]" value="yes" class="sg-toggle-checkbox" <?php checked( $settings['block_admin_targets'] ?? 'no', 'yes' ); ?>>
							<span class="sg-toggle-track sg-toggle-sm"><span class="sg-toggle-thumb"></span></span>
						</label>
					</div>
				</div>

				<div class="sg-form-row">
					<div class="sg-row-label">
						<div class="sg-row-title"><?php esc_html_e( 'Allowed Switcher Roles', 'windcodex-switchuser' ); ?></div>
						<div class="sg-row-hint"><?php esc_html_e( 'Only users with at least one checked role can initiate a switch session. Leave all unchecked to allow any user with the edit_users capability (WordPress default).', 'windcodex-switchuser' ); ?></div>
					</div>
					<div class="sg-row-body">
						<input type="hidden"
							   name="switchuser_settings[allowed_switcher_roles]"
							   id="sg-switcher-roles-value"
							   value="<?php echo esc_attr( $switchuser_switcher_csv ); ?>">
						<div class="sg-role-grants-grid" id="sg-switcher-roles-grid">
							<?php foreach ( $switchuser_all_roles as $switchuser_role_slug => $switchuser_role_data ) : ?>
								<label class="sg-role-grant-option">
									<input type="checkbox"
										   class="sg-role-grant-cb"
										   value="<?php echo esc_attr( $switchuser_role_slug ); ?>"
										<?php checked( in_array( $switchuser_role_slug, $switchuser_switcher_roles, true ) ); ?>>
									<?php echo esc_html( translate_user_role( $switchuser_role_data['name'] ) ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="sg-form-row sg-form-row-last">
					<div class="sg-row-label">
						<div class="sg-row-title"><?php esc_html_e( 'Session Duration', 'windcodex-switchuser' ); ?></div>
						<div class="sg-row-hint"><?php esc_html_e( 'How many hours the signed switch cookie stays valid (1–168, up to 7 days). Default: 48.', 'windcodex-switchuser' ); ?></div>
					</div>
					<div class="sg-row-body">
						<div class="sg-session-ttl-wrap">
							<input type="number"
								   name="switchuser_settings[session_ttl_hours]"
								   id="sg-session-ttl"
								   value="<?php echo esc_attr( (int) ( $settings['session_ttl_hours'] ?? 48 ) ); ?>"
								   min="1" max="168" step="1"
								   class="sg-input sg-ttl-input">
							<span class="sg-ttl-unit"><?php esc_html_e( 'hours', 'windcodex-switchuser' ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<!-- ── Integration Points ──────────────────────────────── -->
			<div class="sg-card">
				<div class="sg-card-header">
					<div>
						<div class="sg-card-header-title"><?php esc_html_e( 'Integration Points', 'windcodex-switchuser' ); ?></div>
						<div class="sg-card-header-sub"><?php esc_html_e( 'Choose where switch actions appear across WordPress and WooCommerce.', 'windcodex-switchuser' ); ?></div>
					</div>
				</div>

				<div class="sg-form-row">
					<div class="sg-row-label">
						<div class="sg-row-title"><?php esc_html_e( 'Profile Screen Switch', 'windcodex-switchuser' ); ?></div>
						<div class="sg-row-hint"><?php esc_html_e( 'Shows a "Switch to this user" button on the user profile edit screen.', 'windcodex-switchuser' ); ?></div>
					</div>
					<div class="sg-row-body">
						<label class="sg-toggle-wrap">
							<input type="checkbox" name="switchuser_settings[profile_screen_enabled]" value="yes" class="sg-toggle-checkbox" <?php checked( $settings['profile_screen_enabled'] ?? 'yes', 'yes' ); ?>>
							<span class="sg-toggle-track sg-toggle-sm"><span class="sg-toggle-thumb"></span></span>
						</label>
					</div>
				</div>

				<div class="sg-form-row sg-form-row-last">
					<div class="sg-row-label">
						<div class="sg-row-title"><?php esc_html_e( 'WooCommerce Order Screen Switch', 'windcodex-switchuser' ); ?></div>
						<div class="sg-row-hint"><?php esc_html_e( 'Shows a "Switch to customer" meta box on WooCommerce order edit screens for quick impersonation.', 'windcodex-switchuser' ); ?></div>
					</div>
					<div class="sg-row-body">
						<label class="sg-toggle-wrap">
							<input type="checkbox" name="switchuser_settings[order_screen_enabled]" value="yes" class="sg-toggle-checkbox" <?php checked( $settings['order_screen_enabled'] ?? 'yes', 'yes' ); ?>>
							<span class="sg-toggle-track sg-toggle-sm"><span class="sg-toggle-thumb"></span></span>
						</label>
					</div>
				</div>
			</div>

		</div><!-- /.sg-tab-panel[general] -->

		<div class="sg-footer-bar" id="sg-footer-bar" data-tab-visible="general">
			<div class="sg-footer-left">
				<button type="button" id="sg-save-btn"  class="sg-btn-primary"><span class="sg-btn-label"><?php esc_html_e( 'Save Settings',     'windcodex-switchuser' ); ?></span></button>
				<button type="button" id="sg-reset-btn" class="sg-btn-reset">  <span class="sg-btn-label"><?php esc_html_e( 'Reset to Defaults',  'windcodex-switchuser' ); ?></span></button>
			</div>
			<div class="sg-toast" id="sg-toast"></div>
		</div>
	</form>
</div>

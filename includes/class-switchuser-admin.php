<?php
/**
 * Admin settings for SwitchUser Free.
 *
 * @package SwitchUser_Free
 */

defined( 'ABSPATH' ) || exit;

class SwitchUser_Admin {

	private static ?SwitchUser_Admin $instance = null;

	public static function instance(): SwitchUser_Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . SWITCHUSER_PLUGIN_BASE, array( $this, 'plugin_action_links' ) );
		add_action( 'wp_ajax_switchuser_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_switchuser_reset_settings', array( $this, 'ajax_reset_settings' ) );
	}

	public function register_menu(): void {
		add_menu_page(
			esc_html__( 'SwitchUser', 'windcodex-switchuser' ),
			esc_html__( 'SwitchUser', 'windcodex-switchuser' ),
			'manage_options',
			'switchuser-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-randomize',
			56
		);
	}

	public function register_settings(): void {
		register_setting(
			'switchuser_settings_group',
			'switchuser_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	public function sanitize_settings( array $input ): array {
		$defaults = SwitchUser_Core::get_default_settings();

		// --- session_ttl_hours: clamp to 1–168 ---
		$session_ttl = (int) ( $input['session_ttl_hours'] ?? 48 );
		$session_ttl = max( 1, min( 168, $session_ttl ) );

		// --- allowed_switcher_roles: validate each slug against registered roles ---
		$roles_raw   = sanitize_text_field( $input['allowed_switcher_roles'] ?? '' );
		$role_slugs  = array_filter( array_map( 'sanitize_key', explode( ',', $roles_raw ) ) );
		$valid_roles = array_keys( wp_roles()->roles );
		$clean_roles = implode( ',', array_values( array_intersect( $role_slugs, $valid_roles ) ) );

		return array(
			'enable_user_switching'  => ( ( $input['enable_user_switching'] ?? 'no' ) === 'yes' ) ? 'yes' : 'no',
			'block_admin_targets'    => ( ( $input['block_admin_targets'] ?? 'no' ) === 'yes' ) ? 'yes' : 'no',
			'allowed_switcher_roles' => $clean_roles,
			'session_ttl_hours'      => $session_ttl,
			'order_screen_enabled'   => ( ( $input['order_screen_enabled'] ?? 'yes' ) === 'yes' ) ? 'yes' : 'no',
			'profile_screen_enabled' => ( ( $input['profile_screen_enabled'] ?? 'yes' ) === 'yes' ) ? 'yes' : 'no',
		) + $defaults;
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_switchuser-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'switchuser-admin',
			SWITCHUSER_PLUGIN_URL . 'admin/assets/admin.css',
			array(),
			SWITCHUSER_VERSION
		);

		wp_enqueue_script(
			'switchuser-admin-page',
			SWITCHUSER_PLUGIN_URL . 'admin/assets/admin.js',
			array( 'jquery' ),
			SWITCHUSER_VERSION,
			true
		);

		wp_localize_script(
			'switchuser-admin-page',
			'switchuserAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'saveNonce' => wp_create_nonce( 'switchuser_save_settings' ),
				'resetNonce' => wp_create_nonce( 'switchuser_reset_settings' ),
				'i18n'      => array(
					'saving'      => __( 'Saving...', 'windcodex-switchuser' ),
					'saved'       => __( 'Settings saved!', 'windcodex-switchuser' ),
					'saveError'   => __( 'Could not save. Please try again.', 'windcodex-switchuser' ),
					'resetting'   => __( 'Resetting...', 'windcodex-switchuser' ),
					'resetDone'   => __( 'Settings reset to defaults!', 'windcodex-switchuser' ),
					'confirmReset'=> __( 'Reset all settings to their default values? This cannot be undone.', 'windcodex-switchuser' ),
					'resetError'  => __( 'Could not reset. Please try again.', 'windcodex-switchuser' ),
				),
			)
		);
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'windcodex-switchuser' ) );
		}

		$settings = wp_parse_args( (array) get_option( 'switchuser_settings', array() ), SwitchUser_Core::get_default_settings() );
		require SWITCHUSER_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	public function ajax_save_settings(): void {
		check_ajax_referer( 'switchuser_save_settings', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'windcodex-switchuser' ) ), 403 );
		}

		$input = array();
		if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
			$input = map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' );
		}

		$clean = $this->sanitize_settings( $input );
		$existing = (array) get_option( 'switchuser_settings', array() );
		$merged   = array_merge( $existing, $clean );
		update_option( 'switchuser_settings', $merged );

		wp_send_json_success( array( 'settings' => $clean ) );
	}

	public function ajax_reset_settings(): void {
		check_ajax_referer( 'switchuser_reset_settings', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'windcodex-switchuser' ) ), 403 );
		}

		$defaults = SwitchUser_Core::get_default_settings();
		$existing = (array) get_option( 'switchuser_settings', array() );
		$merged   = array_merge( $existing, $defaults );
		update_option( 'switchuser_settings', $merged );
		wp_send_json_success( array( 'settings' => $defaults ) );
	}

	public function plugin_action_links( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=switchuser-settings' ) ) . '">' . esc_html__( 'Settings', 'windcodex-switchuser' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}

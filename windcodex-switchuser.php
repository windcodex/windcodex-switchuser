<?php
/**
 * @wordpress-plugin
 * Plugin Name:			WindCodex SwitchUser
 * Tagline:				Advanced User Switching with Admin-Only Access Control
 * Description:			Secure one-click user switching for WordPress and WooCommerce with admin-only access control.
 * Version:				1.0.0
 * Author:				WindCodex
 * Author URI:			https://www.windcodex.com
 * License:				GPL v2 or later
 * License URI:			https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:			windcodex-switchuser
 * Domain Path:			/languages
 * Requires at least:	6.9
 * Tested up to:		7.0
 * Requires PHP:		8.1
 *
 * @package SwitchUser_Free
 */

defined( 'ABSPATH' ) || exit;

define( 'SWITCHUSER_VERSION', '1.0.0' );
define( 'SWITCHUSER_PLUGIN_FILE', __FILE__ );
define( 'SWITCHUSER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWITCHUSER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined( 'SWITCHUSER_PLUGIN_BASE' ) ) {
	define( 'SWITCHUSER_PLUGIN_BASE', plugin_basename( __FILE__ ) );
}

require_once SWITCHUSER_PLUGIN_DIR . 'includes/class-switchuser-core.php';
require_once SWITCHUSER_PLUGIN_DIR . 'includes/class-switchuser-admin.php';
require_once SWITCHUSER_PLUGIN_DIR . 'includes/class-switchuser-notices.php';

// --- Pro Active check ---------------------------------------------

function switchuser_check_pro_active(): void {
	if ( defined( 'SWITCHUSER_PRO_VERSION' ) ) {
		add_action( 'admin_notices', 'switchuser_pro_active_notice' );
		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( SWITCHUSER_PLUGIN_BASE );
		}
	}
}
add_action( 'plugins_loaded', 'switchuser_check_pro_active' );

function switchuser_pro_active_notice(): void {
	echo '<div class="notice notice-error"><p>';
	echo wp_kses_post(
		sprintf(
			/* translators: %s: SwitchUser Pro plugin URL */
			__( '<strong>WindCodex SwitchUser</strong> cannot be activated while <strong>SwitchUser Pro</strong> is active.', 'windcodex-switchuser' ),
			'https://www.windcodex.com/switchuser/'
		)
	);
	echo '</p></div>';
}

function switchuser_init(): void {
	// When Pro is loaded, Free should stay passive to avoid duplicate hooks/actions.
	if ( class_exists( 'SwitchUser_Pro_Core' ) ) {
		return;
	}

	SwitchUser_Core::instance();
	if ( is_admin() ) {
		SwitchUser_Admin::instance();
		$sg_notices = new SwitchUser_Notices();
		add_action( 'switchuser_before_settings',         array( $sg_notices, 'render_pro_notice' ),    10 );
		add_action( 'switchuser_before_settings',         array( $sg_notices, 'render_review_notice' ), 20 );
		add_action( 'admin_enqueue_scripts',               array( $sg_notices, 'localize_nonce' ) );
		add_action( 'wp_ajax_switchuser_dismiss_review',  array( $sg_notices, 'ajax_dismiss' ) );
	}
}
add_action( 'plugins_loaded', 'switchuser_init', 30 );

function switchuser_extract_free_settings( array $source ): array {
	$defaults = SwitchUser_Core::get_default_settings();
	$known    = array_intersect_key( $source, $defaults );
	return wp_parse_args( $known, $defaults );
}

register_activation_hook( __FILE__, 'switchuser_activate' );
function switchuser_activate(): void {
	$current_settings = (array) get_option( 'switchuser_settings', array() );

	$merged_settings = array_merge( $current_settings, switchuser_extract_free_settings( $current_settings ) );

	update_option( 'switchuser_settings', $merged_settings );
	update_option( 'switchuser_version', SWITCHUSER_VERSION );

	// Track first activation time for the review request notice.
	if ( ! get_option( 'switchuser_activated_time' ) ) {
		update_option( 'switchuser_activated_time', time() );
	}
}

register_deactivation_hook( __FILE__, 'switchuser_deactivate' );
function switchuser_deactivate(): void {
	wp_clear_scheduled_hook( 'switchuser_daily_cleanup' );
}

<?php
/**
 * Core switching engine for SwitchUser Free.
 *
 * @package SwitchUser_Free
 */

defined( 'ABSPATH' ) || exit;

class SwitchUser_Core {

	private const COOKIE_NAME = 'switchuser_origin';

	private static ?SwitchUser_Core $instance = null;

	public static function instance(): SwitchUser_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function get_default_settings(): array {
		return array(
			'enable_user_switching'  => 'yes',
			'block_admin_targets'    => 'no',
			'allowed_switcher_roles' => '',
			'session_ttl_hours'      => 48,
			'order_screen_enabled'   => 'yes',
			'profile_screen_enabled' => 'yes',
		);
	}

	/**
	 * Returns the cookie TTL in seconds, based on the session_ttl_hours setting.
	 */
	private function get_cookie_ttl(): int {
		$hours = (int) ( $this->settings()['session_ttl_hours'] ?? 48 );
		$hours = max( 1, min( 168, $hours ) );
		return $hours * HOUR_IN_SECONDS;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'handle_actions' ), 1 );
		add_filter( 'user_row_actions', array( $this, 'add_user_row_action' ), 10, 2 );
		add_action( 'show_user_profile', array( $this, 'render_profile_switch_box' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile_switch_box' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_nodes' ), 90 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_toolbar_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_toolbar_assets' ) );
		add_action( 'wp_ajax_switchuser_search_users', array( $this, 'ajax_search_users' ) );
		add_action( 'wp_ajax_switchuser_get_switch_url', array( $this, 'ajax_get_switch_url' ) );
		add_action( 'wp_ajax_switchuser_quick_switch_user', array( $this, 'ajax_quick_switch_user' ) );
		add_action( 'login_message', array( $this, 'render_login_switchback_notice' ) );
		add_action( 'current_screen', array( $this, 'maybe_render_wc_order_switch' ) );
	}

	public function settings(): array {
		return wp_parse_args( (array) get_option( 'switchuser_settings', array() ), self::get_default_settings() );
	}

	public function current_user_can_switch(): bool {
		$settings = $this->settings();
		if ( 'yes' !== ( $settings['enable_user_switching'] ?? 'no' ) ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user || 0 === (int) $user->ID ) {
			return false;
		}

		$roles_csv     = trim( (string) ( $settings['allowed_switcher_roles'] ?? '' ) );
		$allowed_roles = '' !== $roles_csv
			? array_filter( array_map( 'trim', explode( ',', $roles_csv ) ) )
			: array();

		if ( ! empty( $allowed_roles ) ) {
			// Specific roles configured: user must have at least one of them.
			if ( empty( array_intersect( (array) $user->roles, $allowed_roles ) ) ) {
				return false;
			}
		} else {
			// Default: require the edit_users capability.
			if ( ! user_can( $user, 'edit_users' ) ) {
				return false;
			}
		}

		return true;
	}

	public function can_switch_to_user( WP_User $target ): bool {
		$target_id = absint( $target->ID );
		if ( ! $target_id || ! $target->exists() ) {
			return false;
		}

		if ( ! $this->current_user_can_switch() ) {
			return false;
		}

		$settings    = $this->settings();
		$roles_csv   = trim( (string) ( $settings['allowed_switcher_roles'] ?? '' ) );
		$using_roles = '' !== $roles_csv;

		// When specific switcher roles are configured the actor is already trusted;
		// skip the per-target edit_user capability check. The role hierarchy check
		// below still prevents switching up to a higher-privilege account.
		if ( ! $using_roles ) {
			if (
				! current_user_can( 'edit_user', $target_id ) &&
				! current_user_can( 'switch_to_user', $target_id )
			) {
				return false;
			}
		}

		if ( 'yes' === ( $settings['block_admin_targets'] ?? 'no' ) && in_array( 'administrator', (array) $target->roles, true ) ) {
			return false;
		}

		$actor = wp_get_current_user();
		if ( ! $actor instanceof WP_User || ! $actor->exists() ) {
			return false;
		}

		return $this->actor_outranks_target( $actor, $target );
	}

	public function handle_actions(): void {
		$action = $this->get_verified_request_action();
		if ( '' === $action ) {
			return;
		}

		if ( 'switch_to' === $action ) {
			$this->handle_switch_to();
			return;
		}

		if ( 'switch_back' === $action ) {
			$this->handle_switch_back();
			return;
		}

		$this->handle_switch_off();
	}

	private function handle_switch_to(): void {
		check_admin_referer( 'switchuser_switch_to' );

		$target_id = filter_input( INPUT_GET, 'user_id', FILTER_VALIDATE_INT );
		$target_id = $target_id ? absint( $target_id ) : 0;
		$target    = $target_id ? get_user_by( 'id', $target_id ) : false;

		if ( ! $target instanceof WP_User ) {
			wp_die( esc_html__( 'Target user not found.', 'windcodex-switchuser' ) );
		}

		if ( ! $this->can_switch_to_user( $target ) ) {
			wp_die( esc_html__( 'You are not allowed to switch to this user.', 'windcodex-switchuser' ) );
		}

		$current_id    = get_current_user_id();
		$origin_cookie = $this->read_origin_cookie();
		$origin_id     = ! empty( $origin_cookie['origin_id'] ) ? (int) $origin_cookie['origin_id'] : $current_id;
		$actor_id      = ! empty( $origin_cookie['actor_id'] ) ? (int) $origin_cookie['actor_id'] : $current_id;

		$this->write_origin_cookie(
			array(
				'origin_id'   => $origin_id,
				'actor_id'    => $actor_id,
				'switched_to' => $target_id,
				'is_off'      => 0,
			)
		);

		$use_secure_auth_cookie = $this->should_use_secure_cookie();
		wp_clear_auth_cookie();
		wp_set_auth_cookie( $target_id, true, $use_secure_auth_cookie );
		wp_set_current_user( $target_id );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress login hook.
		do_action( 'wp_login', $target->user_login, $target );

		do_action( 'switchuser_switched', array(
			'actor_id'  => $actor_id,
			'origin_id' => $origin_id,
			'target_id' => $target_id,
			'ip'        => $this->get_request_ip(),
			'time'      => current_time( 'mysql', true ),
		) );
		$this->log_switch_event( 'switch_to', $actor_id, $target_id, $origin_id );

		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_GET['redirect_to'] ) ) : '';
		$redirect = $this->sanitize_switchuser_redirect_url( $redirect );
		$redirect = $this->resolve_switch_redirect_for_target( $target, $redirect, user_can( $actor_id, 'manage_options' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function handle_switch_back(): void {
		check_admin_referer( 'switchuser_switch_back' );

		$cookie = $this->read_origin_cookie();
		if ( empty( $cookie['origin_id'] ) ) {
			wp_die( esc_html__( 'No switch session found.', 'windcodex-switchuser' ) );
		}

		$origin_id = (int) $cookie['origin_id'];
		$from_id   = get_current_user_id();
		$origin    = get_user_by( 'id', $origin_id );
		if ( ! $origin instanceof WP_User ) {
			$this->clear_origin_cookie();
			wp_die( esc_html__( 'Original account no longer exists.', 'windcodex-switchuser' ) );
		}

		$use_secure_auth_cookie = $this->should_use_secure_cookie();
		wp_clear_auth_cookie();
		wp_set_auth_cookie( $origin_id, true, $use_secure_auth_cookie );
		wp_set_current_user( $origin_id );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress login hook.
		do_action( 'wp_login', $origin->user_login, $origin );
		$this->clear_origin_cookie();

		do_action( 'switchuser_switched_back', array(
			'actor_id'  => ! empty( $cookie['actor_id'] ) ? (int) $cookie['actor_id'] : $origin_id,
			'origin_id' => $origin_id,
			'from_id'   => $from_id,
			'ip'        => $this->get_request_ip(),
			'time'      => current_time( 'mysql', true ),
		) );
		$this->log_switch_event( 'switch_back', ! empty( $cookie['actor_id'] ) ? (int) $cookie['actor_id'] : $origin_id, $origin_id, $from_id );

		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_GET['redirect_to'] ) ) : '';
		$redirect = $this->sanitize_switchuser_redirect_url( $redirect );
		if ( $this->is_admin_actor_from_cookie( $cookie ) ) {
			$redirect = $this->get_admin_actor_redirect( 'back' );
		} elseif ( empty( $redirect ) ) {
			$redirect = admin_url();
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	private function handle_switch_off(): void {
		check_admin_referer( 'switchuser_switch_off' );

		$cookie = $this->read_origin_cookie();
		if ( empty( $cookie['origin_id'] ) ) {
			wp_die( esc_html__( 'No switch session found.', 'windcodex-switchuser' ) );
		}

		$cookie['is_off'] = 1;
		$this->log_switch_event( 'switch_off', ! empty( $cookie['actor_id'] ) ? (int) $cookie['actor_id'] : (int) $cookie['origin_id'], get_current_user_id(), (int) $cookie['origin_id'] );
		$this->write_origin_cookie( $cookie );
		wp_logout();

		$target = wp_login_url();
		$target = remove_query_arg( 'redirect_to', $target );
		$target = $this->sanitize_switchuser_redirect_url( $target );
		wp_safe_redirect( $target );
		exit;
	}

	public function add_user_row_action( array $actions, WP_User $user ): array {
		if ( ! $this->can_switch_to_user( $user ) || $user->ID === get_current_user_id() ) {
			return $actions;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? wp_sanitize_redirect( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: admin_url( 'users.php' );

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'switchuser_action' => 'switch_to',
					'user_id'            => (int) $user->ID,
					'redirect_to'        => $request_uri,
				),
				admin_url( 'users.php' )
			),
			'switchuser_switch_to'
		);

		$actions['switchuser_switch_to'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Switch To', 'windcodex-switchuser' ) . '</a>';
		return $actions;
	}

	public function render_profile_switch_box( WP_User $profile_user ): void {
		$settings = $this->settings();
		if ( 'yes' !== ( $settings['profile_screen_enabled'] ?? 'yes' ) ) {
			return;
		}

		if ( ! $profile_user instanceof WP_User || ! $this->can_switch_to_user( $profile_user ) || $profile_user->ID === get_current_user_id() ) {
			return;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'switchuser_action' => 'switch_to',
					'user_id'            => (int) $profile_user->ID,
					'redirect_to'        => admin_url(),
				),
				admin_url( 'users.php' )
			),
			'switchuser_switch_to'
		);

		echo '<h2>' . esc_html__( 'SwitchUser', 'windcodex-switchuser' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tr><th>' . esc_html__( 'Impersonation', 'windcodex-switchuser' ) . '</th><td>';
		echo '<a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html__( 'Switch to this user', 'windcodex-switchuser' ) . '</a>';
		echo '</td></tr></table>';
	}

	public function maybe_render_wc_order_switch( $screen = null ): void {
		$settings = $this->settings();
		if ( 'yes' !== ( $settings['order_screen_enabled'] ?? 'yes' ) ) {
			return;
		}

		// Show the meta box to anyone who can manage users so they see
		// actionable messaging (e.g. "enable switching in settings") rather
		// than a blank panel. Permission details are handled inside the renderer.
		if ( ! is_admin() || ! current_user_can( 'edit_users' ) ) {
			return;
		}

		if ( ! $screen && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}
		if ( ! $screen ) {
			return;
		}
		$screen_id = is_object( $screen ) && isset( $screen->id ) ? (string) $screen->id : '';
		$is_wc_order_screen = in_array( $screen_id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true )
			|| false !== strpos( $screen_id, 'wc-orders' );
		if ( ! $is_wc_order_screen ) {
			return;
		}

		// Priority 1 so we register before WooCommerce's own side meta boxes.
		add_action( 'add_meta_boxes_shop_order', array( $this, 'register_order_meta_box' ), 1 );
		add_action( 'add_meta_boxes', array( $this, 'register_order_meta_box_hpos' ), 1 );
		// JS move to top — overrides any saved per-user drag order.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_metabox_position_script' ) );
	}

	public function register_order_meta_box(): void {
		add_meta_box(
			'switchuser-order-switch',
			esc_html__( 'Switch to Customer', 'windcodex-switchuser' ),
			array( $this, 'render_order_meta_box' ),
			'shop_order',
			'side',
			'high'
		);
	}

	public function register_order_meta_box_hpos(): void {
		add_meta_box(
			'switchuser-order-switch-hpos',
			esc_html__( 'Switch to Customer', 'windcodex-switchuser' ),
			array( $this, 'render_order_meta_box_hpos' ),
			'woocommerce_page_wc-orders',
			'side',
			'high'
		);
	}

	public function enqueue_order_metabox_position_script(): void {
		wp_enqueue_script(
			'switchuser-order-metabox-position',
			SWITCHUSER_PLUGIN_URL . 'admin/assets/order-metabox-position.js',
			array(),
			SWITCHUSER_VERSION,
			true
		);
	}

	public function render_order_meta_box( WP_Post $post ): void {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $post->ID ) : null;
		$this->render_order_switch_markup( $order );
	}

	public function render_order_meta_box_hpos(): void {
		$order_id = filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );
		$order_id = $order_id ? absint( $order_id ) : 0;
		$order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		$this->render_order_switch_markup( $order );
	}

	private function render_order_switch_markup( $order ): void {
		if ( ! $order || ! method_exists( $order, 'get_user_id' ) ) {
			echo '<p>' . esc_html__( 'Order not found.', 'windcodex-switchuser' ) . '</p>';
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'This order is from a guest customer.', 'windcodex-switchuser' ) . '</p>';
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			echo '<p>' . esc_html__( 'Customer account not found.', 'windcodex-switchuser' ) . '</p>';
			return;
		}

		// Explain specifically why switching is blocked so admins can act on it.
		if ( ! $this->can_switch_to_user( $user ) ) {
			$settings = $this->settings();

			if ( 'yes' !== ( $settings['enable_user_switching'] ?? 'no' ) ) {
				echo '<p>' . wp_kses(
					sprintf(
						/* translators: %s: URL to the SwitchUser settings page */
						__( 'User switching is <strong>disabled</strong>. Enable it in <a href="%s">SwitchUser Settings</a>.', 'windcodex-switchuser' ),
						esc_url( admin_url( 'admin.php?page=switchuser-settings' ) )
					),
					array( 'strong' => array(), 'a' => array( 'href' => array() ) )
				) . '</p>';
				return;
			}

			if ( 'yes' === ( $settings['block_admin_targets'] ?? 'no' ) && in_array( 'administrator', (array) $user->roles, true ) ) {
				echo '<p>' . esc_html__( 'Switching into administrator accounts is blocked by your settings.', 'windcodex-switchuser' ) . '</p>';
				return;
			}

			if ( ! $this->actor_outranks_target( wp_get_current_user(), $user ) ) {
				echo '<p>' . esc_html__( 'You cannot switch into an account with equal or higher privileges than your own.', 'windcodex-switchuser' ) . '</p>';
				return;
			}

			echo '<p>' . esc_html__( 'You do not have permission to switch to this customer.', 'windcodex-switchuser' ) . '</p>';
			return;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'switchuser_action' => 'switch_to',
					'user_id'            => $user_id,
					'redirect_to'        => home_url( '/' ),
				),
				admin_url( 'users.php' )
			),
			'switchuser_switch_to'
		);

		// Resolve role display name.
		$role_key  = (string) ( $user->roles[0] ?? '' );
		$role_name = '';
		$wp_roles  = wp_roles();
		if ( $role_key && ! empty( $wp_roles->roles[ $role_key ]['name'] ) ) {
			$role_name = translate_user_role( $wp_roles->roles[ $role_key ]['name'] );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() returns safe HTML.
		$avatar = get_avatar( $user->ID, 40, '', esc_attr( $user->display_name ), array( 'class' => 'sg-ob-avatar' ) );

		echo '<div class="sg-ob-wrap">';
		echo '<div class="sg-ob-customer">';
		echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="sg-ob-info">';
		echo '<span class="sg-ob-name">' . esc_html( $user->display_name ) . '</span>';
		if ( $role_name ) {
			echo '<span class="sg-ob-role">' . esc_html( $role_name ) . '</span>';
		}
		echo '<span class="sg-ob-email">' . esc_html( $user->user_email ) . '</span>';
		echo '</div>';
		echo '</div>';
		echo '<a class="button button-primary sg-ob-btn" href="' . esc_url( $url ) . '">'
			. '<span class="dashicons dashicons-randomize sg-ob-btn-icon"></span>'
			. esc_html__( 'Switch to Customer', 'windcodex-switchuser' )
			. '</a>';
		echo '</div>';
	}

	public function add_admin_bar_nodes( WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$cookie = $this->read_origin_cookie();
		if ( ! empty( $cookie['origin_id'] ) ) {
			$switched_to = wp_get_current_user();
			$title       = sprintf(
				/* translators: %s: user display name */
				esc_html__( 'Viewing as %s', 'windcodex-switchuser' ),
				esc_html( $switched_to->display_name )
			);

			$wp_admin_bar->add_node(
				array(
					'id'    => 'switchuser-viewing',
					'title' => $title,
				)
			);

			$back_url = wp_nonce_url(
				add_query_arg( 'switchuser_action', 'switch_back', admin_url() ),
				'switchuser_switch_back'
			);
			$off_url = wp_nonce_url(
				add_query_arg( 'switchuser_action', 'switch_off', admin_url() ),
				'switchuser_switch_off'
			);

			$wp_admin_bar->add_node(
				array(
					'parent' => 'switchuser-viewing',
					'id'     => 'switchuser-switch-back',
					'title'  => esc_html__( 'Switch Back', 'windcodex-switchuser' ),
					'href'   => $back_url,
				)
			);

			$wp_admin_bar->add_node(
				array(
					'parent' => 'switchuser-viewing',
					'id'     => 'switchuser-switch-off',
					'title'  => esc_html__( 'Switch Off', 'windcodex-switchuser' ),
					'href'   => $off_url,
				)
			);
		}

		if ( ! $this->current_user_can_switch() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'switchuser-search',
			'title' => esc_html__( 'Switch User', 'windcodex-switchuser' ),
				'href'  => '#',
				'meta'  => array( 'class' => 'switchuser-search-trigger' ),
			)
		);
	}

	public function enqueue_toolbar_assets( string $hook = '' ): void {
		if ( ! is_admin_bar_showing() ) {
			return;
		}
		if ( ! $this->current_user_can_switch() && empty( $this->read_origin_cookie()['origin_id'] ) ) {
			return;
		}

		wp_enqueue_style(
			'switchuser-toolbar',
			SWITCHUSER_PLUGIN_URL . 'admin/assets/admin.css',
			array(),
			SWITCHUSER_VERSION
		);

		wp_enqueue_script(
			'switchuser-toolbar',
			SWITCHUSER_PLUGIN_URL . 'admin/assets/admin.js',
			array( 'jquery' ),
			SWITCHUSER_VERSION,
			true
		);

		wp_localize_script(
			'switchuser-toolbar',
			'switchuserData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'switchuser_search_users' ),
				'currentUrl' => '',
				'i18n'    => array(
					'prompt'       => esc_html__( 'Search user...', 'windcodex-switchuser' ),
					'noResults'    => esc_html__( 'No matching users found.', 'windcodex-switchuser' ),
					'choosePrompt' => esc_html__( 'Pick a result by number:', 'windcodex-switchuser' ),
					'searching'    => esc_html__( 'Searching...', 'windcodex-switchuser' ),
				),
			)
		);
	}

	public function ajax_search_users(): void {
		check_ajax_referer( 'switchuser_search_users', 'nonce' );
		if ( ! $this->current_user_can_switch() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'windcodex-switchuser' ) ), 403 );
		}

		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['term'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : '';

		$users = get_users(
			array(
				'number'         => 8,
				'search'         => '*' . esc_attr( $term ) . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'orderby'        => 'display_name',
				'order'          => 'ASC',
			)
		);

		$results = array();
		foreach ( $users as $user ) {
			if ( ! $user instanceof WP_User || (int) $user->ID === get_current_user_id() || ! $this->can_switch_to_user( $user ) ) {
				continue;
			}

			$target_redirect = $this->resolve_switch_redirect_for_target( $user, $redirect_to, user_can( get_current_user_id(), 'manage_options' ) );
			$role_key        = (string) ( $user->roles[0] ?? '' );
			$role_name       = $role_key;
			$all_roles       = wp_roles();
			if ( $role_key && $all_roles instanceof WP_Roles && ! empty( $all_roles->roles[ $role_key ]['name'] ) ) {
				$role_name = (string) $all_roles->roles[ $role_key ]['name'];
			}
			$results[] = array(
				'id'    => (int) $user->ID,
				'name'  => (string) $user->display_name,
				'email' => (string) $user->user_email,
				'role'  => (string) $role_name,
				'label' => $user->display_name . ' (' . $user->user_email . ') (' . $role_name . ')',
				'url'   => $this->build_switch_url( (int) $user->ID, $target_redirect ),
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	public function ajax_get_switch_url(): void {
		check_ajax_referer( 'switchuser_search_users', 'nonce' );
		if ( ! $this->current_user_can_switch() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'windcodex-switchuser' ) ), 403 );
		}

		$target_id = filter_input( INPUT_POST, 'user_id', FILTER_VALIDATE_INT );
		$target_id = $target_id ? absint( $target_id ) : 0;
		$target    = $target_id ? get_user_by( 'id', $target_id ) : false;
		if ( ! $target instanceof WP_User || ! $this->can_switch_to_user( $target ) ) {
			wp_send_json_error( array( 'message' => __( 'Target user not found.', 'windcodex-switchuser' ) ), 404 );
		}

		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : '';

		wp_send_json_success(
			array(
				'url' => $this->build_switch_url( $target_id, $redirect_to ),
			)
		);
	}

	public function ajax_quick_switch_user(): void {
		check_ajax_referer( 'switchuser_search_users', 'nonce' );
		if ( ! $this->current_user_can_switch() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'windcodex-switchuser' ) ), 403 );
		}

		$target_id = filter_input( INPUT_POST, 'user_id', FILTER_VALIDATE_INT );
		$target_id = $target_id ? absint( $target_id ) : 0;
		$target    = $target_id ? get_user_by( 'id', $target_id ) : false;
		if ( ! $target instanceof WP_User || ! $this->can_switch_to_user( $target ) ) {
			wp_send_json_error( array( 'message' => __( 'Target user not found.', 'windcodex-switchuser' ) ), 404 );
		}

		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : '';
		$redirect_to = $this->sanitize_switchuser_redirect_url( $redirect_to );

		$current_id    = get_current_user_id();
		$origin_cookie = $this->read_origin_cookie();
		$origin_id     = ! empty( $origin_cookie['origin_id'] ) ? (int) $origin_cookie['origin_id'] : $current_id;
		$actor_id      = ! empty( $origin_cookie['actor_id'] ) ? (int) $origin_cookie['actor_id'] : $current_id;

		$this->write_origin_cookie(
			array(
				'origin_id'   => $origin_id,
				'actor_id'    => $actor_id,
				'switched_to' => $target_id,
				'is_off'      => 0,
			)
		);

		$use_secure_auth_cookie = $this->should_use_secure_cookie();
		wp_clear_auth_cookie();
		wp_set_auth_cookie( $target_id, true, $use_secure_auth_cookie );
		wp_set_current_user( $target_id );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress login hook.
		do_action( 'wp_login', $target->user_login, $target );

		do_action( 'switchuser_switched', array(
			'actor_id'  => $actor_id,
			'origin_id' => $origin_id,
			'target_id' => $target_id,
			'ip'        => $this->get_request_ip(),
			'time'      => current_time( 'mysql', true ),
		) );
		$this->log_switch_event( 'switch_to', $actor_id, $target_id, $origin_id );

		$redirect = $this->resolve_switch_redirect_for_target( $target, $redirect_to, user_can( $actor_id, 'manage_options' ) );
		wp_send_json_success( array( 'redirect' => $redirect ) );
	}

	private function build_switch_url( int $user_id, string $redirect_to ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'switchuser_action' => 'switch_to',
					'user_id'            => $user_id,
					'redirect_to'        => $redirect_to,
				),
				admin_url( 'users.php' )
			),
			'switchuser_switch_to'
		);
	}

	public function render_login_switchback_notice( string $message ): string {
		$cookie = $this->read_origin_cookie();
		if ( empty( $cookie['origin_id'] ) || empty( $cookie['is_off'] ) ) {
			return $message;
		}

		$url = wp_nonce_url(
			add_query_arg( 'switchuser_action', 'switch_back', admin_url() ),
			'switchuser_switch_back'
		);

		$notice  = '<p class="message switchuser-login-notice">';
		$notice .= esc_html__( 'You switched off your session.', 'windcodex-switchuser' ) . ' ';
		$notice .= '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Switch back now', 'windcodex-switchuser' ) . '</a>';
		$notice .= '</p>';

		return $notice . $message;
	}

	public function get_request_ip(): string {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$server_value = isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) ) : '';
			if ( '' === $server_value ) {
				continue;
			}
			$ip = trim( explode( ',', $server_value )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '';
	}

	public function read_origin_cookie(): array {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return array();
		}

		$raw   = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		$parts = explode( '.', $raw, 2 );
		if ( 2 !== count( $parts ) ) {
			return array();
		}

		$payload = $this->base64url_decode( $parts[0] );
		if ( '' === $payload ) {
			return array();
		}

		$sig_expected = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		if ( ! hash_equals( $sig_expected, $parts[1] ) ) {
			return array();
		}

		$data = json_decode( $payload, true );
		if ( ! is_array( $data ) || empty( $data['exp'] ) || time() > (int) $data['exp'] ) {
			return array();
		}

		return $data;
	}

	private function write_origin_cookie( array $data ): void {
		$ttl  = $this->get_cookie_ttl();
		$data = array_merge(
			array(
				'origin_id'   => 0,
				'actor_id'    => 0,
				'switched_to' => 0,
				'is_off'      => 0,
				'exp'         => time() + $ttl,
			),
			$data
		);

		$payload = wp_json_encode( $data );
		if ( ! is_string( $payload ) ) {
			return;
		}

		$encoded = $this->base64url_encode( $payload );
		$sig     = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		$value   = $encoded . '.' . $sig;

		setcookie(
			self::COOKIE_NAME,
			$value,
			array(
				'expires'  => time() + $ttl,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $this->should_use_secure_cookie(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::COOKIE_NAME ] = $value;
	}

	private function clear_origin_cookie(): void {
		setcookie(
			self::COOKIE_NAME,
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $this->should_use_secure_cookie(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private function should_use_secure_cookie(): bool {
		if ( force_ssl_admin() || is_ssl() ) {
			return true;
		}

		$home_scheme = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) );
		$site_scheme = strtolower( (string) wp_parse_url( site_url( '/' ), PHP_URL_SCHEME ) );
		if ( 'https' !== $home_scheme && 'https' !== $site_scheme ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$forwarded_proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] )
			? strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) )
			: '';
		if ( 'https' === $forwarded_proto ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$https = isset( $_SERVER['HTTPS'] )
			? strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTPS'] ) ) )
			: '';
		if ( '' !== $https && 'off' !== $https ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$cf_visitor = isset( $_SERVER['HTTP_CF_VISITOR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_CF_VISITOR'] ) )
			: '';
		if ( false !== strpos( $cf_visitor, '"scheme":"https"' ) ) {
			return true;
		}

		return false;
	}

	private function resolve_switch_redirect_for_target( WP_User $target, string $requested_redirect, bool $is_admin_actor = false ): string {
		$requested_redirect = $this->sanitize_switchuser_redirect_url( esc_url_raw( $requested_redirect ) );
		$can_access_admin = user_can( $target, 'edit_posts' ) || user_can( $target, 'manage_woocommerce' ) || user_can( $target, 'manage_options' );
		if ( $is_admin_actor ) {
			return $this->get_admin_actor_redirect( 'switched' );
		}

		if ( '' !== $requested_redirect ) {
			$request_path = (string) wp_parse_url( $requested_redirect, PHP_URL_PATH );
			$is_admin_path = false !== strpos( $request_path, '/wp-admin/' );
			if ( ! $is_admin_path || $can_access_admin ) {
				return $requested_redirect;
			}
		}

		$target_roles = (array) $target->roles;
		if ( in_array( 'customer', $target_roles, true ) || in_array( 'subscriber', $target_roles, true ) ) {
			if ( function_exists( 'wc_get_page_permalink' ) ) {
				$myaccount_url = wc_get_page_permalink( 'myaccount' );
				if ( ! empty( $myaccount_url ) ) {
					return esc_url_raw( $myaccount_url );
				}
			}
			return home_url( '/' );
		}

		return admin_url();
	}

	private function get_admin_actor_redirect( string $context = 'switched' ): string {
		if ( 'back' === $context ) {
			return admin_url();
		}
		return admin_url();
	}

	private function sanitize_switchuser_redirect_url( string $url ): string {
		if ( '' === $url ) {
			return '';
		}
		return remove_query_arg( array( 'switchuser_action', '_wpnonce' ), $url );
	}

	private function get_request_nonce(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- Nonce is verified in get_verified_request_action() via wp_verify_nonce().
		if ( isset( $_GET['_wpnonce'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['_wpnonce'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			return sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) );
		}
		return '';
	}

	private function get_requested_action(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified in get_verified_request_action() via wp_verify_nonce().
		return isset( $_GET['switchuser_action'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( wp_unslash( (string) $_GET['switchuser_action'] ) )
			: '';
	}

	private function get_verified_request_action(): string {
		$nonce = $this->get_request_nonce();
		if ( '' === $nonce ) {
			return '';
		}

		$requested_action = $this->get_requested_action();
		if ( '' === $requested_action || ! in_array( $requested_action, array( 'switch_to', 'switch_back', 'switch_off' ), true ) ) {
			return '';
		}

		if ( ! wp_verify_nonce( $nonce, 'switchuser_' . $requested_action ) ) {
			return '';
		}

		return $requested_action;
	}

	private function is_admin_actor_from_cookie( array $cookie ): bool {
		$actor_id = isset( $cookie['actor_id'] ) ? absint( $cookie['actor_id'] ) : 0;
		if ( ! $actor_id ) {
			return false;
		}
		return user_can( $actor_id, 'manage_options' );
	}

	private function actor_outranks_target( WP_User $actor, WP_User $target ): bool {
		if ( (int) $actor->ID === (int) $target->ID ) {
			return false;
		}

		$actor_is_admin  = user_can( $actor, 'manage_options' );
		$target_is_admin = user_can( $target, 'manage_options' );

		// Site admins can switch into any account that lacks admin privileges.
		if ( $actor_is_admin ) {
			return ! $target_is_admin;
		}

		// Target must never be an admin or have user-management powers.
		if ( $target_is_admin || user_can( $target, 'edit_users' ) ) {
			return false;
		}

		// Non-admin actors who hold edit_users (e.g. WooCommerce Shop Manager) can
		// switch into any account that lacks admin/edit_users capabilities.
		// We check this BEFORE the level-cap comparison because third-party roles
		// (e.g. WooCommerce) often omit level_X capabilities entirely.
		if ( user_can( $actor, 'edit_users' ) ) {
			return true;
		}

		// Last resort: compare levels read from the role definition (not user meta,
		// which can be stale or missing).
		$actor_level  = $this->get_user_role_level( $actor );
		$target_level = $this->get_user_role_level( $target );

		return $actor_level > $target_level;
	}

	private function get_user_role_level( WP_User $user ): int {
		$wp_roles  = wp_roles();
		$max_level = 0;
		foreach ( (array) $user->roles as $role_slug ) {
			$role = $wp_roles->get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}
			for ( $i = 10; $i >= 0; $i-- ) {
				if ( ! empty( $role->capabilities[ 'level_' . $i ] ) ) {
					$max_level = max( $max_level, $i );
					break;
				}
			}
		}
		return $max_level;
	}

	private function log_switch_event( string $action, int $actor_id, int $target_id, int $origin_id ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'SwitchUser %1$s actor=%2$d target=%3$d origin=%4$d ip=%5$s',
				sanitize_key( $action ),
				$actor_id,
				$target_id,
				$origin_id,
				$this->get_request_ip()
			)
		);
	}

	private function base64url_decode( string $data ): string {
		$raw = strtr( $data, '-_', '+/' );
		$pad = strlen( $raw ) % 4;
		if ( $pad ) {
			$raw .= str_repeat( '=', 4 - $pad );
		}
		$decoded = base64_decode( $raw, true );
		return false === $decoded ? '' : $decoded;
	}
}

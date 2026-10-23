<?php
/**
 * tirreno security framework
 *
 * @package           TirrenoWordPressTracker
 * @author            team@tirreno.com
 * @copyright         (c) tirreno technologies sàrl (https://www.tirreno.com)
 * @license           https://opensource.org/licenses/bsd-3-clause BSD License
 * @link              https://www.tirreno.com tirreno(tm)
 *
 * @wordpress-plugin
 * Plugin Name:       tirreno for WordPress
 * Plugin URI:        https://github.com/tirrenotechnologies/tirreno-wordpress-tracker/
 * Description:       Plugin implements the tirreno tracking API via TirrenoTracker library.
 * Version:           0.9.1
 * Requires at least: 5.6
 * Requires PHP:      8.0
 * Author:            team@tirreno.com
 * Author URI:        https://www.tirreno.com
 * Text Domain:       tirreno
 * License:           BSD-3
 * License URI:       https://opensource.org/licenses/bsd-3-clause
 * Update URI:        https://github.com/tirrenotechnologies/tirreno-wordpress-tracker/
 * Requires Plugins:


	Plugin implements the tirreno tracking API via TirrenoTracker library.

	For more information, see: https://github.com/tirrenotechnologies/tirreno-wordpress-tracker/
								https://github.com/tirrenotechnologies/tirreno-php-tracker/
 */

namespace TirrenoWordPressTracker;

use WP_User;
use WP_Post;
use WP_Comment;

final class TirrenoTracker {
	private \TirrenoTracker $tracker;
	private int $event_priority = 10;

	public function __construct() {
		$this->set_menu();

		if ( ! $this->check_params() ) {
			return;
		}

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$this->tracker = $this->get_tracker_with_defaults();

		$this->add_actions();
	}

	public static function boot(): void {
		add_action( 'init', array( self::class, 'init' ) );
	}

	public static function init(): void {
		new self();
	}

	private function set_menu(): void {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_filter( 'plugin_row_meta', array( $this, 'add_row_links' ), 10, 2 );
		}
	}

	private function get_links(): array {
		return array(
			__( 'tirreno.com', 'tirreno' )                    => 'https://www.tirreno.com',
			__( 'Developer guide', 'tirreno' )                => 'https://github.com/tirrenotechnologies/DEVELOPMENT.md',
			__( 'Admin guide', 'tirreno' )                    => 'https://github.com/tirrenotechnologies/ADMIN.md',
			__( 'Operator guide', 'tirreno' )                 => 'https://github.com/tirrenotechnologies/OPERATOR.md',
			__( 'tirreno source code (GitHub)', 'tirreno' )   => 'https://github.com/tirrenotechnologies/tirreno',
			__( 'tirreno for WordPress plugin', 'tirreno' )   => 'https://github.com/tirrenotechnologies/tirreno-wordpress-tracker',
		);
	}

	private function link_html( string $label, string $url ): string {
		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	public function add_row_links( array $links, string $file ): array {
		if ( plugin_basename( dirname( __DIR__ ) . '/tirreno-wordpress-tracker.php' ) !== $file ) {
			return $links;
		}

		foreach ( $this->get_links() as $label => $url ) {
			$links[] = $this->link_html( $label, $url );
		}

		return $links;
	}

	public function add_menu(): void {
		add_menu_page(
			'tirreno for WordPress',
			'tirreno',
			'manage_options',
			'tirreno-wordpress-plugin',
			array( $this, 'settings_page' ),
			plugins_url( '/images/icon.png', __FILE__ )
		);
	}

	public function register_settings(): void {
		register_setting(
			'tirreno-settings-group',
			'tirreno_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $v ) {
					$v = trim( (string) $v );
					return '' === $v ? '' : esc_url_raw( $v, array( 'http', 'https' ) );
				},
				'default'           => '',
			)
		);
		register_setting(
			'tirreno-settings-group',
			'tracking_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<p class="tirreno-links">
			<?php
			$tirreno_links = array();
			foreach ( $this->get_links() as $label => $url ) {
				$tirreno_links[] = $this->link_html( $label, $url );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo implode( ' | ', $tirreno_links ); // escaped previously in link_html()
			?>
		</p>

		<p>
			<?php esc_html_e( 'This plugin sends security events from this site to your tirreno instance to keep an audit log, detect threats and record field edit history. It covers logins and failed logins, logouts, registrations, password and email changes, profile, content and comment edits, searches and error pages. tirreno scores each account against its rules, so attacks such as credential stuffing, account takeover, spam and scanning can be reviewed in the tirreno console.', 'tirreno' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Events are sent once per request, when the page finishes. AJAX, cron and REST API requests are not tracked. Detection only starts once rules have weights in the tirreno console.', 'tirreno' ); ?>
		</p>

		<?php if ( defined( 'TIRRENO_URL' ) || defined( 'TRACKING_ID' ) ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php esc_html_e( 'Values defined in wp-config.php take precedence over the fields below:', 'tirreno' ); ?>
				<?php if ( defined( 'TIRRENO_URL' ) ) : ?>
					<br /><code>TIRRENO_URL</code> = <code><?php echo esc_html( (string) TIRRENO_URL ); ?></code>
				<?php endif; ?>
				<?php if ( defined( 'TRACKING_ID' ) ) : ?>
					<br /><code>TRACKING_ID</code> = <code><?php echo esc_html( substr( (string) TRACKING_ID, 0, 6 ) . '…' ); ?></code>
				<?php endif; ?>
			</p>
		</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'tirreno-settings-group' ); ?>
			<table class="form-table">
				<tr valign="top">
				<th scope="row">Sensor URL</th>
				<td>
					<input type="text" class="regular-text" name="tirreno_url" value="<?php echo esc_attr( get_option( 'tirreno_url' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Full sensor URL of your tirreno, including the trailing slash, for example https://tirreno.example.com/sensor/.', 'tirreno' ); ?></p>
				</td>
				</tr>
				<tr valign="top">
				<th scope="row">Tracking ID</th>
				<td>
					<input type="text" class="regular-text" name="tracking_id" value="<?php echo esc_attr( get_option( 'tracking_id' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'The API key shown on the API page of the tirreno console.', 'tirreno' ); ?></p>
				</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		</div>
		<?php
	}

	private function check_params(): bool {
		[$url, $id] = $this->get_config();
		if ( ! $url || ! $id ) {
			add_action(
				'admin_notices',
				function () {
					$class   = 'notice notice-error';
					$message = __( 'Please define TIRRENO_URL and TRACKING_ID in wp-config.php or via UI settings', 'tirreno' );

					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
				}
			);

			return false;
		}

		return true;
	}

	private function get_config(): array {
		$url = strval( defined( 'TIRRENO_URL' ) ? TIRRENO_URL : get_option( 'tirreno_url', '' ) );
		$id  = strval( defined( 'TRACKING_ID' ) ? TRACKING_ID : get_option( 'tracking_id', '' ) );

		return array( $url, $id );
	}

	private function add_actions(): void {
		add_action( 'shutdown', array( $this, 'shutdown' ), 10 );

		// setEventTypePageError.
		add_filter( 'status_header', array( $this, 'on_status_header' ), 10, 4 );
		add_filter( 'wp_redirect_status', array( $this, 'on_redirect_status' ), 10, 2 );

		// setEventTypePageView.
		// setEventTypePageSearch.
		add_action( 'template_redirect', array( $this, 'on_template_redirect' ), 10 );

		// setEventTypeAccountEmailChange.
		// setEventTypeAccountEdit.
		add_action( 'profile_update', array( $this, 'on_profile_update' ), 10, 2 );

		add_action( 'set_user_role', array( $this, 'set_event_type_account_edit' ), 10, 3 );
		add_action( 'delete_user', array( $this, 'set_event_type_account_edit_on_delete' ), 10, 3 );

		add_action( 'save_post', array( $this, 'set_event_type_page_edit' ), 10, 3 );
		add_action( 'edit_comment', array( $this, 'set_event_type_page_edit_edit_comment' ), 10, 2 );
		add_action( 'wp_insert_comment', array( $this, 'set_event_type_page_edit_insert_comment' ), 10, 2 );

		add_action( 'wp_trash_post', array( $this, 'set_event_type_page_delete' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'set_event_type_page_delete' ), 10, 1 );

		add_action( 'wp_login', array( $this, 'set_event_type_account_login' ), 10, 2 );

		add_action( 'wp_logout', array( $this, 'set_event_type_account_logout' ), 10 );

		add_action( 'wp_login_failed', array( $this, 'set_event_type_account_login_fail' ), 10, 1 );

		add_action( 'user_register', array( $this, 'set_event_type_account_registration' ), 10, 1 );

		add_action( 'wp_set_password', array( $this, 'set_event_type_account_password_change_wp' ), 10, 3 );
		add_action( 'password_reset', array( $this, 'set_event_type_account_password_change' ), 10, 2 );

		add_action( 'updated_user_meta', array( $this, 'on_updated_user_meta' ), 10, 4 );
		add_action( 'added_user_meta', array( $this, 'on_added_user_meta' ), 10, 4 );
		add_action( 'deleted_user_meta', array( $this, 'on_deleted_user_meta' ), 10, 4 );

		add_action( 'updated_post_meta', array( $this, 'on_updated_post_meta' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'on_added_post_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'on_deleted_post_meta' ), 10, 4 );

		add_action( 'wp_set_comment_status', array( $this, 'on_comment_status' ), 10, 2 );
	}

	private function get_tracker_with_defaults(): \TirrenoTracker {
		[$url, $id] = $this->get_config();
		$tracker    = new \TirrenoTracker( $url, $id );

		// set page title later
		$user = wp_get_current_user();

		if ( $user && $user instanceof WP_User && $user->exists() ) {
			// or $user->ID?
			$username = $user->user_login;
			if ( $username && is_string( $username ) ) {
				$tracker->setUserName( $username );
			}

			$email = $user->user_email;
			if ( $email && is_string( $email ) ) {
				$tracker->setEmailAddress( $email );
			}

			$fullname_needed = true;

			$firstname = $user->user_firstname;
			if ( $firstname && is_string( $firstname ) ) {
				$tracker->setFirstName( $firstname );
				$fullname_needed = false;
			}

			$lastname = $user->user_lastname;
			if ( $lastname && is_string( $lastname ) ) {
				$tracker->setLastName( $lastname );
				$fullname_needed = false;
			}

			$phone_number = get_user_meta( $user->ID, 'billing_phone', true );
			$phone_number = $phone_number ? $phone_number : get_user_meta( $user->ID, 'phone', true );
			if ( $phone_number && is_string( $phone_number ) ) {
				$tracker->setPhoneNumber( $phone_number );
			}

			$display_name = $user->display_name;
			if ( $fullname_needed && $display_name && is_string( $display_name ) ) {
				$tracker->setFullName( $display_name );
			}
		}

		return $tracker;
	}

	public function shutdown(): void {
		$event_type = $this->tracker->getEventType();
		$username   = $this->tracker->getUserName();

		$this->tracker->setPageTitle( is_admin() ? get_admin_page_title() : get_the_title() );
		$this->tracker->track();

		do_action( 'tirreno_tracker_event_sent', $event_type, $username );
	}

	public function on_status_header( string $header, int $code, string $desc, string $protocol ): string {
		$this->tracker->setHttpCode( $code );
		if ( $code >= 400 && $this->allow_priority( 0 ) ) {
			$this->tracker->setEventTypePageError();
		}

		return $header;
	}

	public function on_redirect_status( int $status, string $location ): int {
		$this->tracker->setHttpCode( $status );

		return $status;
	}

	public function on_template_redirect(): void {
		if ( is_404() && $this->allow_priority( 0 ) ) {
			$this->tracker->setEventTypePageError();

			return;
		}

		if ( is_search() && $this->allow_priority( 4 ) ) {
			$field_id = 'search';
			$search   = sanitize_text_field( get_search_query( false ) );

			$payload = array(
				'field_id' => $field_id,
				'value'    => $search,
			);

			$this->tracker->setEventTypePageSearch();
			$this->tracker->setPayload( $payload );

			return;
		}

		if ( $this->allow_priority( 5 ) ) {
			$this->tracker->setEventTypePageView();
		}
	}

	public function set_event_type_page_edit( int $post_id, WP_Post $post, bool $update ): void {
		if ( ! $this->allow_priority( 3 ) ) {
			return;
		}

		$this->tracker->setEventTypePageEdit();
	}

	public function set_event_type_page_edit_edit_comment( int $comment_id, array $data ): void {
		if ( ! $this->allow_priority( 3 ) ) {
			return;
		}

		$this->tracker->setEventTypePageEdit();
	}

	public function set_event_type_page_edit_insert_comment( int $id, WP_Comment $comment ): void {
		if ( ! $this->allow_priority( 3 ) ) {
			return;
		}

		$this->tracker->setEventTypePageEdit();
	}

	public function set_event_type_account_edit( int $user_id, string $role, array $old_roles ): void {
		if ( ! $this->allow_priority( 2 ) ) {
			return;
		}

		$this->tracker->setEventTypeAccountEdit();
	}

	public function set_event_type_account_edit_on_delete( int $id, ?int $reassign, WP_User $user ): void {
		if ( ! $this->allow_priority( 2 ) ) {
			return;
		}

		$this->tracker->setEventTypeAccountEdit();
	}

	public function set_event_type_page_delete( int $post_id ): void {
		if ( ! $this->allow_priority( 3 ) ) {
			return;
		}

		$this->tracker->setEventTypePageDelete();
	}

	public function set_event_type_account_login( string $user_login, WP_User $user ): void {
		if ( ! $this->allow_priority( 1 ) ) {
			return;
		}

		$this->tracker->setEventTypeAccountLogin();
		$this->set_identity( $user );
	}

	public function set_event_type_account_logout(): void {
		if ( ! $this->allow_priority( 1 ) ) {
			return;
		}

		$this->tracker->setEventTypeAccountLogout();
	}

	public function set_event_type_account_login_fail( string $username ): void {
		if ( ! $this->allow_priority( 1 ) ) {
			return;
		}

		$this->tracker->setEventTypeAccountLoginFail();

		// Nobody is logged in during a failed attempt, so the identity set at init is empty. Attribute the event to the account that was targeted.
		$login = trim( $username );
		if ( '' === $login ) {
			return;
		}

		$user = get_user_by( 'login', $login );
		if ( ! $user && is_email( $login ) ) {
			$user = get_user_by( 'email', $login );
		}

		if ( $user instanceof WP_User ) {
			$this->set_identity( $user );
			return;
		}

		// Unknown username: keep it, since guessed names are a signal in themselves, if nothing usable survives sanitizing, the IP-based fallback applies.
		$guessed = sanitize_user( $login );
		if ( '' !== $guessed ) {
			$this->tracker->setUserName( $guessed );
		}
	}

	private function set_identity( WP_User $user ): void {
		if ( $user->user_login ) {
			$this->tracker->setUserName( (string) $user->user_login );
		}

		if ( $user->user_email ) {
			$this->tracker->setEmailAddress( (string) $user->user_email );
		}
	}

	// keep 1 arg
	public function set_event_type_account_registration( int $user_id ): void {
		if ( ! $this->allow_priority( 1 ) ) {
			return;
		}

		$this->tracker->setEventTypeAccountRegistration();
	}

	// keep 2 args
	public function on_profile_update( int $user_id, WP_User $old_user_data ): void {
		$old_email = isset( $old_user_data->user_email ) ? (string) $old_user_data->user_email : '';
		$new_user  = get_userdata( $user_id );
		$new_email = ( $new_user instanceof WP_User && ! empty( $new_user->user_email ) ) ? (string) $new_user->user_email : '';

		if ( ( '' !== $new_email || '' !== $old_email ) && $new_email !== $old_email && $this->allow_priority( 1 ) ) {
			$this->tracker->setEventTypeAccountEmailChange();
			$this->tracker->setPayload(
				array(
					'new_value' => $new_email,
					'old_value' => $old_email,
				)
			);

			return;
		}

		if ( ! $this->allow_priority( 2 ) ) {
			return;
		}

		$this->tracker->setEventTypeAccountEdit();
	}

	public function on_updated_user_meta( int $meta_id, int $object_id, string $meta_key, mixed $_meta_value ): void {
		$item = array(
			'field_id'    => 'user_meta.' . strval( $meta_key ),
			'field_name'  => 'user_meta.' . strval( $meta_key ),
			'old_value'   => null,
			'new_value'   => $_meta_value ?? '',
			'parent_id'   => $object_id,
			'parent_name' => 'wp_user',
		);

		$this->add_field_history_item( $item );
	}

	public function on_added_user_meta( int $meta_id, int $object_id, string $meta_key, mixed $_meta_value ): void {
		$item = array(
			'field_id'    => 'user_meta.' . strval( $meta_key ),
			'field_name'  => 'user_meta.' . strval( $meta_key ),
			'old_value'   => null,
			'new_value'   => $_meta_value ?? '',
			'parent_id'   => $object_id,
			'parent_name' => 'wp_user',
		);

		$this->add_field_history_item( $item );
	}

	public function on_deleted_user_meta( array $meta_ids, int $object_id, string $meta_key, mixed $_meta_value ): void {
		$item = array(
			'field_id'    => 'user_meta.' . strval( $meta_key ),
			'field_name'  => 'user_meta.' . strval( $meta_key ),
			'old_value'   => $_meta_value,
			'new_value'   => '',
			'parent_id'   => $object_id,
			'parent_name' => 'wp_user',
		);

		$this->add_field_history_item( $item );
	}

	public function on_updated_post_meta( int $meta_id, int $object_id, string $meta_key, mixed $_meta_value ): void {
		if ( '' !== $meta_key && '_' === $meta_key[0] ) {
			return;
		}

		$post_type = get_post_type( $post_id ) ?: 'post';

		$item = array(
			'field_id'    => 'post_meta.' . strval( $meta_key ),
			'field_name'  => 'post_meta.' . strval( $meta_key ),
			'old_value'   => null,
			'new_value'   => $_meta_value ?? '',
			'parent_id'   => $object_id,
			'parent_name' => $post_type,
		);

		$this->add_field_history_item( $item );
	}

	public function on_added_post_meta( int $meta_id, int $object_id, string $meta_key, mixed $_meta_value ): void {
		if ( '' !== $meta_key && '_' === $meta_key[0] ) {
			return;
		}

		$post_type = get_post_type( $post_id ) ?: 'post';

		$item = array(
			'field_id'    => 'post_meta.' . strval( $meta_key ),
			'field_name'  => 'post_meta.' . strval( $meta_key ),
			'old_value'   => null,
			'new_value'   => $_meta_value ?? '',
			'parent_id'   => $object_id,
			'parent_name' => $post_type,
		);

		$this->add_field_history_item( $item );
	}

	public function on_deleted_post_meta( array $meta_ids, int $object_id, string $meta_key, mixed $_meta_value ): void {
		if ( '' !== $meta_key && '_' === $meta_key[0] ) {
			return;
		}

		$post_type = get_post_type( $post_id ) ?: 'post';

		$item = array(
			'field_id'    => 'post_meta.' . strval( $meta_key ),
			'field_name'  => 'post_meta.' . strval( $meta_key ),
			'old_value'   => $_meta_value,
			'new_value'   => null ?? '',
			'parent_id'   => $object_id,
			'parent_name' => $post_type,
		);

		$this->add_field_history_item( $item );
	}

	public function on_comment_status( int $comment_id, string $status ): void {
		$item = array(
			'field_id'    => 'comment_status',
			'field_name'  => 'comment_status',
			'old_value'   => null,
			'new_value'   => $status,
			'parent_id'   => $comment_id,
			'parent_name' => 'comment',
		);

		$this->add_field_history_item( $item );
	}

	public function set_event_type_account_password_change_wp( string $password, int $user_id, WP_User $old_user_data ): void {
		if ( ! $this->allow_priority( 1 ) ) {
			return;
		}

		$this->tracker->setEventTypeAccountPasswordChange();
	}

	public function set_event_type_account_password_change( WP_User $old_user_data, string $password ): void {
		if ( ! $this->allow_priority( 1 ) ) {
			return;
		}

		$this->tracker->setEventTypeAccountPasswordChange();
	}

	private function set_event_type_field_edit(): void {
		if ( ! $this->allow_priority( 1 ) ) {
			return;
		}

		$this->tracker->setEventTypeFieldEdit();
	}

	private function add_field_history_item( array $item ): void {
		$this->set_event_type_field_edit();

		$this->tracker->addFieldHistory( $item );
	}

	private function allow_priority( int $priority ): bool {
		$allow = $this->event_priority >= $priority;
		if ( $allow ) {
			$this->event_priority = $priority;
		}

		return $allow;
	}
}

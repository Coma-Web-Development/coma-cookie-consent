<?php
/**
 * Server-side consent record (GDPR accountability).
 *
 * Stores one row per explicit consent action (accept / reject / save) in a
 * custom table. No raw IP is kept — only a salted hash — so the log itself is
 * privacy-preserving while still providing proof of consent.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCC_Consent_Log {

	const TABLE = 'ccc_consent_log';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function table_exists() {
		global $wpdb;
		$t = self::table();
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
	}

	/** Create/upgrade the log table. Called on activation. */
	public static function create_table() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consent_id VARCHAR(36) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			necessary TINYINT(1) NOT NULL DEFAULT 1,
			functional TINYINT(1) NOT NULL DEFAULT 0,
			analytics TINYINT(1) NOT NULL DEFAULT 0,
			marketing TINYINT(1) NOT NULL DEFAULT 0,
			version INT NOT NULL DEFAULT 1,
			lang VARCHAR(5) NOT NULL DEFAULT '',
			action VARCHAR(12) NOT NULL DEFAULT '',
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			ua VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY consent_id (consent_id),
			KEY created_at (created_at)
		) $charset;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function register_routes() {
		register_rest_route( 'coma-cc/v1', '/log', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => '__return_true', // public: consent is given by anonymous visitors
		) );
	}

	public function handle( WP_REST_Request $req ) {
		$opts = wp_parse_args( get_option( CCC_OPTION, array() ), ccc_default_settings() );
		if ( empty( $opts['log_consent'] ) ) {
			return new WP_REST_Response( array( 'logged' => false ), 200 );
		}

		$body = $req->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_REST_Response( array( 'logged' => false ), 200 );
		}

		$choices = isset( $body['choices'] ) && is_array( $body['choices'] ) ? $body['choices'] : array();

		global $wpdb;
		$wpdb->insert( self::table(), array(
			'consent_id' => substr( sanitize_text_field( $body['id'] ?? '' ), 0, 36 ),
			'created_at' => current_time( 'mysql', true ),
			'necessary'  => 1,
			'functional' => ! empty( $choices['functional'] ) ? 1 : 0,
			'analytics'  => ! empty( $choices['analytics'] ) ? 1 : 0,
			'marketing'  => ! empty( $choices['marketing'] ) ? 1 : 0,
			'version'    => (int) ( $body['version'] ?? 1 ),
			'lang'       => substr( sanitize_text_field( $body['lang'] ?? '' ), 0, 5 ),
			'action'     => substr( sanitize_text_field( $body['action'] ?? '' ), 0, 12 ),
			'ip_hash'    => self::ip_hash(),
			'ua'         => substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
		) );

		return new WP_REST_Response( array( 'logged' => true ), 201 );
	}

	/** Salted, non-reversible hash of the visitor IP (no raw IP stored). */
	private static function ip_hash() {
		$ip = '';
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$ip = explode( ',', $_SERVER[ $k ] )[0];
				break;
			}
		}
		$ip = trim( $ip );
		return $ip ? hash( 'sha256', wp_salt( 'auth' ) . '|' . $ip ) : '';
	}

	/** Total number of logged consents (for the admin screen). */
	public static function count() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	}
}

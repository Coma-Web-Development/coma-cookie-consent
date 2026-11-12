<?php
/**
 * Plugin Name: Coma Cookie Consent
 * Plugin URI:  https://coma.lv
 * Description: GDPR-compliant cookie consent banner with Google Consent Mode v2. Multilingual (LV / RU / EN / LT / ET), auto-detects the active language.
 * Version:     1.7.5
 * Author:      Coma
 * Author URI:  https://coma.lv
 * License:     GPL-2.0+
 * Text Domain: coma-cc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCC_VERSION',    '1.7.5' );
define( 'CCC_DB_VERSION', '1' ); // bump when the consent-log schema changes
define( 'CCC_PATH',    plugin_dir_path( __FILE__ ) );
define( 'CCC_URL',     plugin_dir_url( __FILE__ ) );
define( 'CCC_OPTION',  'coma_cookie_consent_settings' );

require_once CCC_PATH . 'includes/strings.php';
require_once CCC_PATH . 'includes/class-consent-log.php';
require_once CCC_PATH . 'admin/class-admin.php';
require_once CCC_PATH . 'frontend/class-frontend.php';

function ccc_init() {
	ccc_maybe_upgrade();
	new CCC_Frontend();
	new CCC_Consent_Log();
	if ( is_admin() ) {
		new CCC_Admin();
	}
}
add_action( 'plugins_loaded', 'ccc_init' );

/**
 * Run schema upgrades when the plugin is updated by replacing files (which does
 * NOT fire the activation hook). dbDelta is idempotent and gated behind a stored
 * version so it only runs when needed.
 */
function ccc_maybe_upgrade() {
	if ( get_option( 'ccc_db_version' ) === CCC_DB_VERSION ) {
		return;
	}
	CCC_Consent_Log::create_table();
	// Only mark the upgrade done once the table actually exists, so a failed
	// dbDelta is retried on the next request instead of being silently skipped.
	if ( CCC_Consent_Log::table_exists() ) {
		update_option( 'ccc_db_version', CCC_DB_VERSION );
	}
}

/**
 * Default settings. Per-language content (title/description/privacy URL) is seeded
 * from the built-in translations so the banner works out of the box in every
 * supported language. Nothing here is site-specific.
 */
function ccc_default_settings() {
	$strings  = ccc_strings();
	$defaults = array(
		// Global behaviour.
		'default_lang'     => 'lv',          // fallback when visitor language is unsupported
		'enable_analytics' => true,
		'enable_marketing' => true,
		'banner_position'  => 'bottom',
		'revisit_button'   => true,
		'align_selector'   => '',            // CSS selector of the theme's content container to align the banner with
		// Colors. Empty background/text keeps the built-in dark banner; set them for a custom palette.
		'accent_color'     => '#0094C8',  // primary buttons & links
		'accent2_color'    => '',         // secondary accent (toggle "on" state); empty = same as accent
		'background_color' => '',         // banner & modal background; empty = built-in theme
		'text_color'       => '',         // body text; empty = built-in theme
		// Google Analytics.
		'ga_measurement_id' => '',           // e.g. G-XXXXXXXXXX — leave empty if GA is loaded elsewhere
		'load_ga'           => false,        // when true, the plugin loads gtag.js itself (consent-aware)
		// Compliance.
		'block_trackers'        => true,     // hold non-Google tracker scripts until consent
		'block_google'          => false,    // also hold GTM / gtag.js / analytics.js until consent (Consent Mode alone otherwise)
		'youtube_nocookie'      => false,    // rewrite YouTube embeds to youtube-nocookie.com
		'consent_duration_days' => 180,      // how long a choice is remembered (re-prompt after)
		'consent_version'       => 1,        // bump to force everyone to re-consent
		'log_consent'           => true,     // store a server-side record of each choice
		'gating_region'         => 'global', // 'global' = everyone; 'eea' = deny+banner only in EEA/UK
	);

	// Per-language content fields.
	foreach ( ccc_languages() as $lang ) {
		$defaults[ "banner_title_$lang" ]       = $strings[ $lang ]['content']['banner_title'];
		$defaults[ "banner_description_$lang" ] = $strings[ $lang ]['content']['banner_description'];
		$defaults[ "privacy_page_url_$lang" ]   = '';   // site-specific, set per install
	}

	return $defaults;
}

register_activation_hook( __FILE__, 'ccc_activate' );
function ccc_activate() {
	$existing = get_option( CCC_OPTION, array() );
	// Merge so upgrades gain new keys without wiping configured values.
	update_option( CCC_OPTION, array_merge( ccc_default_settings(), (array) $existing ) );
	CCC_Consent_Log::create_table();
}

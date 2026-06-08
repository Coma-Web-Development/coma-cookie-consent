<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCC_Admin {

	private $options;

	public function __construct() {
		$this->options = wp_parse_args( get_option( CCC_OPTION, array() ), ccc_default_settings() );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu() {
		add_options_page(
			'Cookie Consent',
			'Cookie Consent',
			'manage_options',
			'coma-cookie-consent',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting( 'ccc_group', CCC_OPTION, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ) {
		$langs = ccc_languages();

		$default_lang = $input['default_lang'] ?? 'lv';
		if ( ! in_array( $default_lang, $langs, true ) ) {
			$default_lang = 'lv';
		}

		$out = array(
			'default_lang'      => $default_lang,
			'enable_analytics'  => ! empty( $input['enable_analytics'] ),
			'enable_marketing'  => ! empty( $input['enable_marketing'] ),
			'banner_position'   => in_array( $input['banner_position'] ?? 'bottom', array( 'bottom', 'top' ), true ) ? $input['banner_position'] : 'bottom',
			'accent_color'      => sanitize_hex_color( $input['accent_color'] ?? '#0094C8' ),
			'accent2_color'     => sanitize_hex_color( $input['accent2_color'] ?? '' ) ?: '',
			'background_color'  => sanitize_hex_color( $input['background_color'] ?? '' ) ?: '',
			'text_color'        => sanitize_hex_color( $input['text_color'] ?? '' ) ?: '',
			'revisit_button'    => ! empty( $input['revisit_button'] ),
			'ga_measurement_id' => sanitize_text_field( $input['ga_measurement_id'] ?? '' ),
			'load_ga'           => ! empty( $input['load_ga'] ),
			// Compliance.
			'block_trackers'        => ! empty( $input['block_trackers'] ),
			'consent_duration_days' => max( 1, min( 730, (int) ( $input['consent_duration_days'] ?? 180 ) ) ),
			'consent_version'       => max( 1, (int) ( $input['consent_version'] ?? 1 ) ),
			'log_consent'           => ! empty( $input['log_consent'] ),
			'gating_region'         => in_array( $input['gating_region'] ?? 'global', array( 'global', 'eea' ), true ) ? $input['gating_region'] : 'global',
		);

		foreach ( $langs as $lang ) {
			$out[ "banner_title_$lang" ]       = sanitize_text_field( $input[ "banner_title_$lang" ] ?? '' );
			$out[ "banner_description_$lang" ] = sanitize_textarea_field( $input[ "banner_description_$lang" ] ?? '' );
			$out[ "privacy_page_url_$lang" ]   = esc_url_raw( $input[ "privacy_page_url_$lang" ] ?? '' );
		}

		return $out;
	}

	public function render_page() {
		$o       = $this->options;
		$strings = ccc_strings();
		$opt     = CCC_OPTION;
		?>
		<div class="wrap">
			<h1>Cookie Consent</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'ccc_group' ); ?>

				<h2>Vispārīgie iestatījumi / General</h2>
				<table class="form-table">
					<tr>
						<th>Noklusējuma valoda<br><span style="font-weight:400;color:#666">Default / fallback language</span></th>
						<td>
							<select name="<?php echo $opt; ?>[default_lang]">
								<?php foreach ( ccc_languages() as $lang ) : ?>
									<option value="<?php echo esc_attr( $lang ); ?>" <?php selected( $o['default_lang'] ?? 'lv', $lang ); ?>>
										<?php echo esc_html( $strings[ $lang ]['label'] ); ?> (<?php echo esc_html( strtoupper( $lang ) ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">Used when the visitor's language isn't LV / RU / EN. The banner auto-detects language via Polylang, WPML, or the site locale.</p>
						</td>
					</tr>
					<tr>
						<th>Banera pozīcija / Position</th>
						<td>
							<select name="<?php echo $opt; ?>[banner_position]">
								<option value="bottom" <?php selected( $o['banner_position'] ?? 'bottom', 'bottom' ); ?>>Apakšā / Bottom</option>
								<option value="top" <?php selected( $o['banner_position'] ?? 'bottom', 'top' ); ?>>Augšā / Top</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Akcenta krāsa / Accent color</th>
						<td><input type="color" name="<?php echo $opt; ?>[accent_color]" value="<?php echo esc_attr( $o['accent_color'] ?? '#0094C8' ); ?>" />
							<span class="description">Primary buttons &amp; links</span></td>
					</tr>
					<tr>
						<th>Akcents 2 / Accent 2</th>
						<td><input type="text" name="<?php echo $opt; ?>[accent2_color]" value="<?php echo esc_attr( $o['accent2_color'] ?? '' ); ?>" class="regular-text" placeholder="#5cc2b2 — empty = same as accent" />
							<p class="description">Secondary accent — the toggle "on" state. Leave empty to match the main accent.</p></td>
					</tr>
					<tr>
						<th>Fona krāsa / Background</th>
						<td><input type="text" name="<?php echo $opt; ?>[background_color]" value="<?php echo esc_attr( $o['background_color'] ?? '' ); ?>" class="regular-text" placeholder="#f4f4f4 — empty = built-in dark theme" />
							<p class="description">Banner &amp; modal background. Empty keeps the built-in theme. Set this for a custom (e.g. light) banner.</p></td>
					</tr>
					<tr>
						<th>Teksta krāsa / Text</th>
						<td><input type="text" name="<?php echo $opt; ?>[text_color]" value="<?php echo esc_attr( $o['text_color'] ?? '' ); ?>" class="regular-text" placeholder="#2c2c2c — empty = built-in" />
							<p class="description">Body text colour. Used together with a custom background.</p></td>
					</tr>
					<tr>
						<th>Kategorijas / Categories</th>
						<td>
							<label><input type="checkbox" name="<?php echo $opt; ?>[enable_analytics]" value="1" <?php checked( ! empty( $o['enable_analytics'] ) ); ?> /> Analytics</label><br>
							<label><input type="checkbox" name="<?php echo $opt; ?>[enable_marketing]" value="1" <?php checked( ! empty( $o['enable_marketing'] ) ); ?> /> Marketing</label>
						</td>
					</tr>
					<tr>
						<th>Iestatījumu poga / Revisit button</th>
						<td><label><input type="checkbox" name="<?php echo $opt; ?>[revisit_button]" value="1" <?php checked( ! empty( $o['revisit_button'] ) ); ?> /> Show the floating cookie-settings button</label></td>
					</tr>
				</table>

				<h2>Google Analytics</h2>
				<table class="form-table">
					<tr>
						<th>Measurement ID</th>
						<td><input type="text" name="<?php echo $opt; ?>[ga_measurement_id]" value="<?php echo esc_attr( $o['ga_measurement_id'] ?? '' ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX" /></td>
					</tr>
					<tr>
						<th>Load GA from this plugin</th>
						<td>
							<label><input type="checkbox" name="<?php echo $opt; ?>[load_ga]" value="1" <?php checked( ! empty( $o['load_ga'] ) ); ?> /> Inject gtag.js (consent-aware)</label>
							<p class="description"><strong>Leave OFF</strong> if Google Analytics or GTM is already loaded by the theme or another plugin — otherwise you'll load GA twice. The Consent Mode v2 signal is sent regardless of this setting.</p>
						</td>
					</tr>
				</table>

				<h2>Atbilstība / Compliance</h2>
				<table class="form-table">
					<tr>
						<th>Block trackers until consent</th>
						<td>
							<label><input type="checkbox" name="<?php echo $opt; ?>[block_trackers]" value="1" <?php checked( ! empty( $o['block_trackers'] ) ); ?> /> Hold non-Google tracker scripts (Meta, TikTok, LinkedIn, Hotjar, …) until the visitor opts in</label>
							<p class="description">Google tags are not blocked — Consent Mode v2 governs those. GTM-injected tags should be gated inside GTM with consent triggers.</p>
						</td>
					</tr>
					<tr>
						<th>Remember choice for</th>
						<td>
							<input type="number" min="1" max="730" name="<?php echo $opt; ?>[consent_duration_days]" value="<?php echo esc_attr( (int) ( $o['consent_duration_days'] ?? 180 ) ); ?>" class="small-text" /> days
							<p class="description">After this period the banner is shown again (CNIL/EDPB suggest ~180 days).</p>
						</td>
					</tr>
					<tr>
						<th>Consent version</th>
						<td>
							<input type="number" min="1" name="<?php echo $opt; ?>[consent_version]" value="<?php echo esc_attr( (int) ( $o['consent_version'] ?? 1 ) ); ?>" class="small-text" />
							<p class="description">Increase this to force every visitor to re-consent (e.g. after changing your cookie policy).</p>
						</td>
					</tr>
					<tr>
						<th>Consent log</th>
						<td>
							<label><input type="checkbox" name="<?php echo $opt; ?>[log_consent]" value="1" <?php checked( ! empty( $o['log_consent'] ) ); ?> /> Store a server-side record of each choice</label>
							<p class="description">Proof of consent (timestamp, choices, version, language, hashed IP — no raw IP). <strong><?php echo esc_html( number_format_i18n( CCC_Consent_Log::count() ) ); ?></strong> records stored.</p>
						</td>
					</tr>
					<tr>
						<th>Apply consent to</th>
						<td>
							<select name="<?php echo $opt; ?>[gating_region]">
								<option value="global" <?php selected( $o['gating_region'] ?? 'global', 'global' ); ?>>All visitors (recommended)</option>
								<option value="eea" <?php selected( $o['gating_region'] ?? 'global', 'eea' ); ?>>EEA / UK only</option>
							</select>
							<p class="description">"EEA / UK only" denies by default and shows the banner only in those regions (Consent Mode uses region-scoped defaults; banner targeting needs a country signal such as Cloudflare's, otherwise it shows to everyone to stay safe).</p>
						</td>
					</tr>
				</table>

				<h2>Banera teksts / Banner text per language</h2>
				<p class="description">Leave a field empty to fall back to the built-in default for that language.</p>
				<?php foreach ( ccc_languages() as $lang ) : $L = strtoupper( $lang ); ?>
					<h3 style="margin-bottom:0"><?php echo esc_html( $strings[ $lang ]['label'] ); ?> (<?php echo esc_html( $L ); ?>)</h3>
					<table class="form-table">
						<tr>
							<th>Virsraksts / Title</th>
							<td><input type="text" name="<?php echo $opt; ?>[banner_title_<?php echo $lang; ?>]" value="<?php echo esc_attr( $o[ "banner_title_$lang" ] ?? '' ); ?>" class="large-text" placeholder="<?php echo esc_attr( $strings[ $lang ]['content']['banner_title'] ); ?>" /></td>
						</tr>
						<tr>
							<th>Teksts / Description</th>
							<td><textarea name="<?php echo $opt; ?>[banner_description_<?php echo $lang; ?>]" rows="3" class="large-text" placeholder="<?php echo esc_attr( $strings[ $lang ]['content']['banner_description'] ); ?>"><?php echo esc_textarea( $o[ "banner_description_$lang" ] ?? '' ); ?></textarea></td>
						</tr>
						<tr>
							<th>Privātuma politikas URL<br><span style="font-weight:400;color:#666">Privacy policy URL</span></th>
							<td><input type="url" name="<?php echo $opt; ?>[privacy_page_url_<?php echo $lang; ?>]" value="<?php echo esc_attr( $o[ "privacy_page_url_$lang" ] ?? '' ); ?>" class="large-text" placeholder="https://example.com/privacy/" /></td>
						</tr>
					</table>
				<?php endforeach; ?>

				<?php submit_button( 'Saglabāt / Save' ); ?>
			</form>
		</div>
		<?php
	}
}

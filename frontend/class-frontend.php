<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCC_Frontend {

	private $options;
	private $lang;     // resolved language slug: lv | ru | en | lt | et
	private $t;        // chrome strings for the resolved language
	private $gated;    // is THIS visitor subject to consent gating (banner + blocking)?

	public function __construct() {
		$this->options = wp_parse_args( get_option( CCC_OPTION, array() ), ccc_default_settings() );
		$this->lang    = $this->detect_lang();
		$this->gated   = $this->is_gated();

		$strings  = ccc_strings();
		$this->t  = $strings[ $this->lang ]['chrome'];

		// Consent Mode defaults are emitted for everyone (region-scoped when in EEA mode).
		add_action( 'wp_head', array( $this, 'inject_gcm_defaults' ), 1 );

		// The banner, asset loading and tracker-blocking apply only to gated visitors.
		if ( $this->gated ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
			add_action( 'wp_footer',          array( $this, 'render_banner' ), 1 );
			add_action( 'wp_footer',          array( $this, 'inject_override_styles' ), 99 );
			if ( ! empty( $this->options['block_trackers'] ) || ! empty( $this->options['youtube_nocookie'] ) ) {
				add_action( 'template_redirect', array( $this, 'start_blocking' ), 0 );
			}
		}
	}

	/* ── Region gating ──────────────────────────────────────────────────────── */

	private function is_gated() {
		if ( 'eea' !== ( $this->options['gating_region'] ?? 'global' ) ) {
			return true; // global: gate everyone
		}
		$country = $this->detect_country();
		if ( '' === $country ) {
			return true; // unknown location → gate (safe default)
		}
		return $this->in_eea( $country );
	}

	/** Best-effort visitor country (ISO-2, upper). '' when unknown. Extend via the filter. */
	private function detect_country() {
		$cc = '';
		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) { // Cloudflare
			$cc = $_SERVER['HTTP_CF_IPCOUNTRY'];
		}
		$cc = apply_filters( 'ccc_visitor_country', $cc );
		$cc = strtoupper( substr( (string) $cc, 0, 2 ) );
		return ctype_alpha( $cc ) ? $cc : '';
	}

	private function in_eea( $cc ) {
		$eea = array(
			'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT',
			'LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','IS','LI','NO',
			'GB', // UK — equivalent UK GDPR
		);
		return in_array( $cc, $eea, true );
	}

	/**
	 * Resolve the visitor's current language to a supported slug.
	 *
	 * Order:
	 *   1. Polylang current language (per-request) — its locale first, then its slug
	 *   2. WPML current language   (per-request) — its locale first, then its code
	 *   3. Configured default language
	 *
	 * The multilingual plugin's *locale* is checked before its code because the
	 * code is an arbitrary admin-chosen slug (e.g. a site cloned from a Latvian
	 * install may keep code "lv" for its Estonian language, locale et_EE).
	 *
	 * The WP site locale is deliberately NOT used: many single-language sites run
	 * an English admin locale (en_US) while serving localized content, so trusting
	 * the locale would mislabel the banner. On a single-language site the admin's
	 * explicit "default language" choice wins; multilingual plugins override it
	 * per request once active.
	 */
	private function detect_lang() {
		if ( function_exists( 'pll_current_language' ) ) {
			$match = $this->match_supported( (string) pll_current_language( 'locale' ) )
				?: $this->match_supported( (string) pll_current_language( 'slug' ) );
			if ( $match ) {
				return $match;
			}
		}

		if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
			$wpml_langs = apply_filters( 'wpml_active_languages', null, 'skip_missing=0' );
			$locale     = is_array( $wpml_langs ) ? ( $wpml_langs[ ICL_LANGUAGE_CODE ]['default_locale'] ?? '' ) : '';
			$match      = $this->match_supported( (string) $locale )
				?: $this->match_supported( (string) ICL_LANGUAGE_CODE );
			if ( $match ) {
				return $match;
			}
		}

		$default = $this->options['default_lang'] ?? 'lv';
		return in_array( $default, ccc_languages(), true ) ? $default : 'lv';
	}

	/** Return the supported slug for a language code, or '' if unsupported. */
	private function match_supported( $code ) {
		$code = strtolower( substr( (string) $code, 0, 2 ) );
		return in_array( $code, ccc_languages(), true ) ? $code : '';
	}

	private function get_active_features() {
		return array(
			'hasWoocommerce' => class_exists( 'WooCommerce' ),
		);
	}

	public function enqueue() {
		// No web font is loaded — the banner inherits the site's own fonts (see banner.css).
		wp_enqueue_style(
			'ccc-banner',
			CCC_URL . 'frontend/css/banner.css',
			array(),
			CCC_VERSION
		);

		wp_enqueue_script(
			'ccc-consent',
			CCC_URL . 'frontend/js/consent.js',
			array(),
			CCC_VERSION,
			true
		);

		wp_localize_script( 'ccc-consent', 'cccSettings', array(
			'lang'            => $this->lang,
			'ocdUrl'          => CCC_URL . 'frontend/data/open-cookie-db.json?ver=' . CCC_VERSION,
			'revisitButton'   => ! empty( $this->options['revisit_button'] ),
			'alignSelector'   => (string) ( $this->options['align_selector'] ?? '' ),
			'enableAnalytics' => ! empty( $this->options['enable_analytics'] ),
			'enableMarketing' => ! empty( $this->options['enable_marketing'] ),
			'activeFeatures'  => $this->get_active_features(),
			'consentExpiry'   => max( 1, (int) ( $this->options['consent_duration_days'] ?? 180 ) ),
			'consentVersion'  => (int) ( $this->options['consent_version'] ?? 1 ),
			'blockTrackers'   => ! empty( $this->options['block_trackers'] ),
			'logUrl'          => ! empty( $this->options['log_consent'] ) ? esc_url_raw( rest_url( 'coma-cc/v1/log' ) ) : '',
			'strings'         => array(
				'unclassified' => $this->msg_unclassified(),
				'none'         => $this->msg_none(),
			),
		) );
	}

	private function msg_unclassified() {
		$m = array(
			'lv' => 'Neklasificēta sīkdatne',
			'ru' => 'Неклассифицированный файл cookie',
			'en' => 'Unclassified cookie',
			'lt' => 'Neklasifikuotas slapukas',
			'et' => 'Klassifitseerimata küpsis',
		);
		return $m[ $this->lang ] ?? $m['en'];
	}

	private function msg_none() {
		$m = array(
			'lv' => 'Nav atklātu sīkdatņu',
			'ru' => 'Файлы cookie не обнаружены',
			'en' => 'No cookies detected',
			'lt' => 'Slapukų neaptikta',
			'et' => 'Küpsiseid ei tuvastatud',
		);
		return $m[ $this->lang ] ?? $m['en'];
	}

	public function inject_gcm_defaults() {
		$eea_mode = ( 'eea' === ( $this->options['gating_region'] ?? 'global' ) );
		?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
<?php if ( $eea_mode ) : ?>
/* Region-scoped: granted baseline, denied only in the EEA/UK (Google applies per user location). */
gtag('consent', 'default', {
	'analytics_storage':  'granted',
	'ad_storage':         'granted',
	'ad_user_data':       'granted',
	'ad_personalization': 'granted',
	'functionality_storage': 'granted',
	'security_storage':   'granted'
});
gtag('consent', 'default', {
	'analytics_storage':  'denied',
	'ad_storage':         'denied',
	'ad_user_data':       'denied',
	'ad_personalization': 'denied',
	'functionality_storage': 'denied',
	'security_storage':   'granted',
	'wait_for_update':    500,
	'region': ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','IS','LI','NO','GB']
});
<?php else : ?>
gtag('consent', 'default', {
	'analytics_storage':  'denied',
	'ad_storage':         'denied',
	'ad_user_data':       'denied',
	'ad_personalization': 'denied',
	'functionality_storage': 'denied',
	'security_storage':   'granted',
	'wait_for_update':    500
});
<?php endif; ?>
gtag('set', 'ads_data_redaction', true);
gtag('set', 'url_passthrough', false);
(function(){
	var m = document.cookie.match(/(?:^|;\s*)coma_consent=([^;]+)/);
	if (!m) return;
	try {
		var c = JSON.parse(decodeURIComponent(m[1]));
		gtag('consent', 'update', {
			'analytics_storage':     c.analytics ? 'granted' : 'denied',
			'ad_storage':            c.marketing ? 'granted' : 'denied',
			'ad_user_data':          c.marketing ? 'granted' : 'denied',
			'ad_personalization':    c.marketing ? 'granted' : 'denied',
			'functionality_storage': c.functional ? 'granted' : 'denied',
			'security_storage':      'granted'
		});
	} catch(e) {}
})();
</script>
		<?php
		$this->maybe_load_ga();
	}

	/**
	 * Optionally load gtag.js. Runs AFTER the consent defaults above, so Google
	 * Consent Mode is respected. Off by default — leave it to the site/GTM unless
	 * you explicitly want this plugin to own the GA tag.
	 */
	private function maybe_load_ga() {
		if ( empty( $this->options['load_ga'] ) ) {
			return;
		}
		$id = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) ( $this->options['ga_measurement_id'] ?? '' ) );
		if ( '' === $id ) {
			return;
		}
		?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $id ); ?>"></script>
<script>
gtag('js', new Date());
gtag('config', '<?php echo esc_js( $id ); ?>');
</script>
		<?php
	}

	/* ── Tracker blocking ───────────────────────────────────────────────────────
	 * Hold known NON-Google tracker scripts until consent: rewrite their <script>
	 * tags to type="text/plain" (with src moved to data-ccc-src) so the browser
	 * neither fetches nor executes them. consent.js re-activates the matching
	 * category once the visitor opts in. Google tags are NOT blocked by default —
	 * Consent Mode v2 governs those — unless "block_google" is on, which also holds
	 * GTM / gtag.js / analytics.js. Use it when the GTM container fires non-Google
	 * tags without consent checks (Consent Mode cannot stop those).
	 *
	 * A category may be compound ("analytics+marketing"): the script then runs only
	 * once every listed category is granted.
	 *
	 * Defensive by design: only matched tracker tags are altered; on anything
	 * unexpected the original HTML is returned unchanged.
	 * ---------------------------------------------------------------------------- */

	public function start_blocking() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_robots()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		ob_start( array( $this, 'filter_output' ) );
	}

	/** Signature list: regex (matched against the whole <script> tag) → category. */
	private function block_signatures() {
		$sigs = array(
			// Marketing
			array( 're' => 'connect\.facebook\.net|fbevents\.js|fbq\s*\(',                 'cat' => 'marketing' ),
			array( 're' => 'analytics\.tiktok\.com|tiktok\.com/i18n/pixel|ttq\.(load|page|track)', 'cat' => 'marketing' ),
			array( 're' => 'snap\.licdn\.com|_linkedin_partner_id',                        'cat' => 'marketing' ),
			array( 're' => 's\.pinimg\.com|pintrk\s*\(',                                   'cat' => 'marketing' ),
			array( 're' => 'static\.ads-twitter\.com|twq\s*\(',                            'cat' => 'marketing' ),
			array( 're' => 'sc-static\.net|tr\.snapchat\.com|snaptr\s*\(',                 'cat' => 'marketing' ),
			// Analytics (non-Google)
			array( 're' => 'static\.hotjar\.com|\bhj\s*\(',                                'cat' => 'analytics' ),
			array( 're' => 'clarity\.ms',                                                  'cat' => 'analytics' ),
			array( 're' => 'mc\.yandex\.(ru|com)|ym\s*\(',                                 'cat' => 'analytics' ),
			// WooCommerce order attribution (Sourcebuster sbjs_* cookies)
			array( 're' => 'sourcebuster|order-attribution',                             'cat' => 'marketing' ),
		);

		if ( ! empty( $this->options['block_google'] ) ) {
			// GTM may fire any tag (incl. non-Google pixels), so by default it needs
			// both categories. Narrow it with the filter if the container is gated inside.
			$sigs[] = array( 're' => 'googletagmanager\.com/gtm\.js',                  'cat' => apply_filters( 'ccc_gtm_category', 'analytics+marketing' ) );
			// Google Ads / Floodlight loaders and inline calls (gtag('config','AW-…'), send_to:'AW-…').
			$sigs[] = array( 're' => 'googleadservices\.com|googletagmanager\.com/gtag/js\?id=(AW|DC)-|[\'"](AW|DC)-\d+', 'cat' => 'marketing' );
			$sigs[] = array( 're' => 'googletagmanager\.com/gtag/js|google-analytics\.com/(analytics|ga)\.js', 'cat' => 'analytics' );
		}

		return apply_filters( 'ccc_block_signatures', $sigs );
	}

	/** Iframe signature list: regex (matched against the whole <iframe> tag) → category. */
	private function iframe_signatures() {
		$sigs = array(
			array( 're' => 'draugiem\.lv/(say|api)',                  'cat' => 'marketing' ),
			array( 're' => 'facebook\.com/plugins/',                   'cat' => 'marketing' ),
		);
		if ( ! empty( $this->options['block_google'] ) ) {
			$sigs[] = array( 're' => 'googletagmanager\.com/ns\.html', 'cat' => 'analytics+marketing' );
		}
		return apply_filters( 'ccc_block_iframe_signatures', $sigs );
	}

	public function filter_output( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		if ( ! empty( $this->options['youtube_nocookie'] ) ) {
			$yt = preg_replace( '#(<iframe\b[^>]*\bsrc=["\']?(?:https?:)?//)(?:www\.)?youtube\.com/embed/#i', '$1www.youtube-nocookie.com/embed/', $html );
			$html = ( null === $yt ) ? $html : $yt;
		}

		if ( empty( $this->options['block_trackers'] ) ) {
			return $html;
		}

		$html = $this->block_iframes( $html );

		if ( false === stripos( $html, '<script' ) ) {
			return $html;
		}

		$sigs = $this->block_signatures();

		$out = preg_replace_callback(
			'#<script\b([^>]*)>(.*?)</script>#is',
			function ( $m ) use ( $sigs ) {
				$whole = $m[0];
				// Skip tags we've already neutralised.
				if ( false !== stripos( $m[1], 'data-ccc-block' ) ) {
					return $whole;
				}
				$cat   = '';
				$probe = str_replace( '\\/', '/', $whole ); // also match JS/JSON-escaped URLs (https:\/\/…)
				foreach ( $sigs as $sig ) {
					if ( preg_match( '#' . $sig['re'] . '#i', $probe ) ) {
						$cat = $sig['cat'];
						break;
					}
				}
				if ( '' === $cat ) {
					return $whole;
				}
				$attrs = $m[1];
				$attrs = preg_replace( '#\bsrc\s*=#i', 'data-ccc-src=', $attrs );          // stop the fetch
				$attrs = preg_replace( '#\btype\s*=\s*("[^"]*"|\'[^\']*\'|\S+)#i', '', $attrs ); // drop existing type
				return '<script type="text/plain" data-ccc-block="' . esc_attr( $cat ) . '"' . $attrs . '>' . $m[2] . '</script>';
			},
			$html
		);

		// On any regex failure (e.g. backtrack limit) keep the page intact.
		return ( null === $out ) ? $html : $out;
	}

	/** Hold matched third-party iframes (src → data-ccc-src) until their category is granted. */
	private function block_iframes( $html ) {
		if ( false === stripos( $html, '<iframe' ) ) {
			return $html;
		}
		$sigs = $this->iframe_signatures();
		$out  = preg_replace_callback(
			'#<iframe\b([^>]*)>#i',
			function ( $m ) use ( $sigs ) {
				if ( false !== stripos( $m[1], 'data-ccc-block' ) ) {
					return $m[0];
				}
				foreach ( $sigs as $sig ) {
					if ( preg_match( '#' . $sig['re'] . '#i', $m[0] ) ) {
						$attrs = preg_replace( '#\bsrc\s*=#i', 'data-ccc-src=', $m[1] );
						return '<iframe data-ccc-block="' . esc_attr( $sig['cat'] ) . '"' . $attrs . '>';
					}
				}
				return $m[0];
			},
			$html
		);
		return ( null === $out ) ? $html : $out;
	}

	/** Resolve a per-language content field: saved value, then the built-in default for the language, then English. */
	private function content( $base ) {
		$val = $this->options[ "{$base}_{$this->lang}" ] ?? '';
		if ( '' === trim( (string) $val ) ) {
			$strings = ccc_strings();
			$val     = $strings[ $this->lang ]['content'][ $base ] ?? '';
		}
		if ( '' === trim( (string) $val ) && 'en' !== $this->lang ) {
			$val = $this->options[ "{$base}_en" ] ?? '';
		}
		return (string) $val;
	}

	public function render_banner() {
		$o           = $this->options;
		$t           = $this->t;
		$title       = esc_html( $this->content( 'banner_title' ) );
		$desc        = esc_html( $this->content( 'banner_description' ) );
		$privacy = $this->content( 'privacy_page_url' );
		if ( '' === trim( $privacy ) ) {
			// Fall back to WordPress' native Privacy Policy page (WPML/Polylang
			// return the URL for the current language automatically).
			$privacy = get_privacy_policy_url();
		}
		$privacy_url = esc_url( $privacy );
		$position    = in_array( $o['banner_position'] ?? 'bottom', array( 'bottom', 'top' ), true ) ? $o['banner_position'] : 'bottom';
		$analytics   = ! empty( $o['enable_analytics'] );
		$marketing   = ! empty( $o['enable_marketing'] );
		$revisit     = ! empty( $o['revisit_button'] );

		$categories = array(
			'necessary' => array(
				'label'       => $t['cat_necessary'],
				'description' => $t['cat_necessary_desc'],
				'required'    => true,
			),
			'analytics' => array(
				'label'       => $t['cat_analytics'],
				'description' => $t['cat_analytics_desc'],
				'required'    => false,
			),
			'marketing' => array(
				'label'       => $t['cat_marketing'],
				'description' => $t['cat_marketing_desc'],
				'required'    => false,
			),
		);
		?>

<!-- Coma Cookie Consent -->
<div id="ccc-banner" class="ccc-banner ccc-banner--<?php echo esc_attr( $position ); ?>" role="dialog" aria-label="<?php echo esc_attr( $t['banner_aria'] ); ?>" hidden>
	<div class="ccc-banner__inner">
		<div class="ccc-banner__content">
			<div class="ccc-banner__text">
				<span class="ccc-banner__title"><?php echo $title; ?></span>
				<span class="ccc-banner__desc"><?php echo $desc; ?></span><?php if ( $privacy_url ) : ?> <a class="ccc-banner__more" href="<?php echo $privacy_url; ?>" target="_blank" rel="noopener"><?php echo esc_html( $t['learn_more'] ); ?></a><?php endif; ?>
			</div>
		</div>
		<div class="ccc-banner__actions">
			<button id="ccc-btn-customize" class="ccc-btn ccc-btn--ghost"><?php echo esc_html( $t['customize'] ); ?></button>
			<button id="ccc-btn-reject"    class="ccc-btn ccc-btn--outline"><?php echo esc_html( $t['reject'] ); ?></button>
			<button id="ccc-btn-accept"    class="ccc-btn ccc-btn--primary"><?php echo esc_html( $t['accept'] ); ?></button>
		</div>
	</div>
</div>

<!-- Preferences Modal -->
<div id="ccc-modal" class="ccc-modal" role="dialog" aria-modal="true" aria-labelledby="ccc-modal-title" hidden>
	<div class="ccc-modal__overlay" id="ccc-overlay"></div>
	<div class="ccc-modal__dialog">
		<div class="ccc-modal__header">
			<h2 id="ccc-modal-title" class="ccc-modal__title"><?php echo esc_html( $t['modal_title'] ); ?></h2>
			<button id="ccc-modal-close" class="ccc-modal__close" aria-label="<?php echo esc_attr( $t['close'] ); ?>">&times;</button>
		</div>
		<div class="ccc-modal__body">

			<?php foreach ( $categories as $key => $cat ) : ?>
				<?php if ( $key === 'analytics' && ! $analytics ) continue; ?>
				<?php if ( $key === 'marketing'  && ! $marketing  ) continue; ?>
				<div class="ccc-cat" data-category="<?php echo esc_attr( $key ); ?>">
					<div class="ccc-cat__row">
						<div class="ccc-cat__meta">
							<button class="ccc-cat__expander" aria-expanded="false" aria-controls="ccc-cookies-<?php echo esc_attr( $key ); ?>">
								<span class="ccc-cat__name"><?php echo esc_html( $cat['label'] ); ?></span>
								<svg class="ccc-cat__arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
							</button>
							<p class="ccc-cat__desc"><?php echo esc_html( $cat['description'] ); ?></p>
						</div>
						<div class="ccc-cat__control">
							<?php if ( ! empty( $cat['required'] ) ) : ?>
								<span class="ccc-always-on"><?php echo esc_html( $t['always_on'] ); ?></span>
							<?php else : ?>
								<label class="ccc-toggle" aria-label="<?php echo esc_attr( $cat['label'] ); ?>">
									<input type="checkbox" class="ccc-toggle__input" name="<?php echo esc_attr( $key ); ?>" id="ccc-cat-<?php echo esc_attr( $key ); ?>">
									<span class="ccc-toggle__track"><span class="ccc-toggle__thumb"></span></span>
								</label>
							<?php endif; ?>
						</div>
					</div>
					<div class="ccc-cat__cookies" id="ccc-cookies-<?php echo esc_attr( $key ); ?>" hidden>
						<div class="ccc-table-wrap">
							<table class="ccc-table">
								<thead><tr><th><?php echo esc_html( $t['th_cookie'] ); ?></th><th><?php echo esc_html( $t['th_duration'] ); ?></th><th><?php echo esc_html( $t['th_desc'] ); ?></th></tr></thead>
								<tbody id="ccc-tbody-<?php echo esc_attr( $key ); ?>"></tbody>
							</table>
						</div>
					</div>
				</div>
			<?php endforeach; ?>

		</div>
		<div class="ccc-modal__footer">
			<button id="ccc-btn-reject-all" class="ccc-btn ccc-btn--outline"><?php echo esc_html( $t['reject_all'] ); ?></button>
			<button id="ccc-btn-accept-all" class="ccc-btn ccc-btn--primary"><?php echo esc_html( $t['accept_all'] ); ?></button>
			<button id="ccc-btn-save"       class="ccc-btn ccc-btn--primary"><?php echo esc_html( $t['save'] ); ?></button>
		</div>
	</div>
</div>

<?php if ( $revisit ) : ?>
<button id="ccc-revisit" class="ccc-revisit" aria-label="<?php echo esc_attr( $t['revisit_aria'] ); ?>" hidden>
	<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/></svg>
</button>
<?php endif; ?>
		<?php
	}

	/**
	 * Build the per-site colour palette as CSS custom properties.
	 * Injected in the footer (after the stylesheet) so site values win.
	 * Empty background/text leaves the built-in theme intact.
	 */
	private function color_vars_css() {
		$o       = $this->options;
		$accent  = sanitize_hex_color( $o['accent_color'] ?? '' ) ?: '#0094C8';
		$accent2 = sanitize_hex_color( $o['accent2_color'] ?? '' );
		$bg      = sanitize_hex_color( $o['background_color'] ?? '' );
		$text    = sanitize_hex_color( $o['text_color'] ?? '' );

		$root   = array(
			'--ccc-accent:' . $accent,
			'--ccc-accent-dark:' . $this->darken( $accent, 12 ),
		);
		if ( $accent2 ) {
			$root[] = '--ccc-accent-2:' . $accent2;
		}

		$palette = '';
		if ( $bg ) {
			if ( ! $text ) {
				$text = '#2c2c2c';
			}
			$palette = implode( ';', array(
				'--ccc-bg:' . $bg,
				'--ccc-text:' . $text,
				'--ccc-muted:' . $this->mix( $text, $bg, 45 ),
				'--ccc-border:' . $this->rgba( $text, '0.16' ),
				'--ccc-row-alt:' . $this->mix( $bg, $text, 7 ),
				'--ccc-th-bg:' . $this->mix( $bg, $text, 9 ),
			) ) . ';';
		}

		$css = ':root{' . implode( ';', $root ) . ';' . $palette . '}';
		if ( $palette ) {
			// Apply the same palette to the modal, overriding its built-in light theme.
			$css .= '#ccc-modal{' . $palette . '}';
		}
		return $css;
	}

	private function hex_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	private function rgb_hex( $c ) {
		return sprintf(
			'#%02x%02x%02x',
			max( 0, min( 255, (int) round( $c[0] ) ) ),
			max( 0, min( 255, (int) round( $c[1] ) ) ),
			max( 0, min( 255, (int) round( $c[2] ) ) )
		);
	}

	/** Darken a hex colour by $pct percent. */
	private function darken( $hex, $pct ) {
		$c = $this->hex_rgb( $hex );
		$f = 1 - $pct / 100;
		return $this->rgb_hex( array( $c[0] * $f, $c[1] * $f, $c[2] * $f ) );
	}

	/** Mix colour $a toward $b by $pct percent. */
	private function mix( $a, $b, $pct ) {
		$x = $this->hex_rgb( $a );
		$y = $this->hex_rgb( $b );
		$w = $pct / 100;
		return $this->rgb_hex( array(
			$x[0] * ( 1 - $w ) + $y[0] * $w,
			$x[1] * ( 1 - $w ) + $y[1] * $w,
			$x[2] * ( 1 - $w ) + $y[2] * $w,
		) );
	}

	private function rgba( $hex, $alpha ) {
		$c = $this->hex_rgb( $hex );
		return "rgba({$c[0]},{$c[1]},{$c[2]},{$alpha})";
	}

	public function inject_override_styles() {
		echo '<style id="ccc-vars">' . $this->color_vars_css() . "</style>\n";
		?>
<style id="ccc-overrides">
#ccc-banner[hidden], #ccc-modal[hidden], #ccc-revisit[hidden] { display: none !important; }

html body #ccc-modal button#ccc-modal-close,
html body #ccc-modal button#ccc-modal-close:hover,
html body #ccc-modal button#ccc-modal-close:focus,
html body #ccc-modal button#ccc-modal-close:active {
	background: transparent !important;
	background-color: transparent !important;
	background-image: none !important;
	border: none !important;
	box-shadow: none !important;
	outline: none !important;
}
html body #ccc-modal button.ccc-cat__expander,
html body #ccc-modal button.ccc-cat__expander:hover,
html body #ccc-modal button.ccc-cat__expander:focus,
html body #ccc-modal button.ccc-cat__expander:active {
	background: transparent !important;
	background-color: transparent !important;
	background-image: none !important;
	border: none !important;
	box-shadow: none !important;
	outline: none !important;
}
</style>
		<?php
	}
}

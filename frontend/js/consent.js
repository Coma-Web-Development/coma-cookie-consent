(function () {
	'use strict';

	var COOKIE_NAME   = 'coma_consent';
	var settings      = window.cccSettings || {};
	var COOKIE_EXPIRY = (settings.consentExpiry > 0) ? settings.consentExpiry : 180; // days
	var CONSENT_VER   = settings.consentVersion || 1;
	var LANG          = /^(lv|ru|en|lt|et)$/.test(settings.lang || '') ? settings.lang : 'en';
	var MSG           = settings.strings || {};

	// ── i18n helpers ─────────────────────────────────────────────────────────────

	function t(obj)      { return (obj && (obj[LANG] || obj.en)) || ''; }       // {lv,ru,en,lt,et} → string
	function dur(token)  { return token === 'consent' ? consentDur() : t(DUR[token] || DUR.unknown); } // duration token → string

	// Lifetime of our own consent cookie — follows the "Remember choice for" setting.
	function consentDur() {
		var n = parseInt(COOKIE_EXPIRY, 10) || 180; // wp_localize_script passes it as a string
		if (n === 365) { return t(DUR.y1); }
		if (n === 730) { return t(DUR.y2); }
		var d = n % 10, h = n % 100;
		var one = d === 1 && h !== 11, few = d >= 2 && d <= 4 && (h < 12 || h > 14);
		var words = {
			lv: (d === 1 && h !== 11) ? 'diena' : 'dienas',
			ru: one ? 'день' : (few ? 'дня' : 'дней'),
			en: n === 1 ? 'day' : 'days',
			lt: (d === 1 && h !== 11) ? 'diena' : ((d === 0 || (h >= 11 && h <= 19)) ? 'dienų' : 'dienos'),
			et: n === 1 ? 'päev' : 'päeva'
		};
		return n + ' ' + (words[LANG] || words.en);
	}

	var DUR = {
		session:    { lv: 'Sesija',     ru: 'Сессия',    en: 'Session', lt: 'Seansas', et: 'Seanss' },
		persistent: { lv: 'Pastāvīga',  ru: 'Постоянная', en: 'Persistent', lt: 'Nuolatinis', et: 'Püsiv' },
		min1:       { lv: '1 minūte',   ru: '1 минута',  en: '1 minute', lt: '1 minutė', et: '1 minut' },
		min30:      { lv: '30 minūtes', ru: '30 минут',  en: '30 minutes', lt: '30 minučių', et: '30 minutit' },
		day1:       { lv: '1 diena',    ru: '1 день',    en: '1 day', lt: '1 diena', et: '1 päev' },
		day2:       { lv: '2 dienas',   ru: '2 дня',     en: '2 days', lt: '2 dienos', et: '2 päeva' },
		mo3:        { lv: '3 mēneši',   ru: '3 месяца',  en: '3 months', lt: '3 mėnesiai', et: '3 kuud' },
		mo6:        { lv: '6 mēneši',   ru: '6 месяцев', en: '6 months', lt: '6 mėnesiai', et: '6 kuud' },
		y1:         { lv: '1 gads',     ru: '1 год',     en: '1 year', lt: '1 metai', et: '1 aasta' },
		y2:         { lv: '2 gadi',     ru: '2 года',    en: '2 years', lt: '2 metai', et: '2 aastat' },
		unknown:    { lv: '?',          ru: '?',         en: '?', lt: '?', et: '?' }
	};

	// ── Open Cookie Database (lazy-loaded on first modal open) ───────────────────
	// Compact dataset: { exact:{ name:[catCode,platform,retention] }, wild:[[name,catCode,platform,retention]] }
	// catCode: 0 = necessary, 1 = analytics, 2 = marketing.

	var OCD       = null;
	var ocdState  = 'idle'; // idle | loading | done | failed
	var OCD_CAT   = { 0: 'necessary', 1: 'analytics', 2: 'marketing' };
	var OCD_DESC  = {
		0: { lv: 'Funkcionāla / nepieciešama sīkdatne', ru: 'Функциональная / необходимая cookie', en: 'Functional / necessary cookie', lt: 'Funkcinis / būtinasis slapukas', et: 'Funktsionaalne / vajalik küpsis' },
		1: { lv: 'Analītiskā sīkdatne',                 ru: 'Аналитическая cookie',               en: 'Analytics cookie', lt: 'Analitinis slapukas', et: 'Analüütiline küpsis' },
		2: { lv: 'Mārketinga sīkdatne',                 ru: 'Маркетинговая cookie',               en: 'Marketing cookie', lt: 'Rinkodaros slapukas', et: 'Turundusküpsis' }
	};

	// Map a free-text retention string to a localized duration, falling back to the raw text.
	function normRet(s) {
		if (!s) { return dur('unknown'); }
		var x = String(s).toLowerCase().trim();
		if (/session/.test(x))                 { return dur('session'); }
		if (/persist|permanent|never/.test(x)) { return dur('persistent'); }
		var map = {
			'1 minute': 'min1', '30 minutes': 'min30',
			'1 day': 'day1', '2 days': 'day2',
			'3 months': 'mo3', '6 months': 'mo6',
			'1 year': 'y1', '2 years': 'y2'
		};
		if (map[x]) { return dur(map[x]); }
		return String(s); // raw (English) for anything we don't have a localized token for
	}

	function ocdEntry(rec, name) {
		var cat = OCD_CAT[rec[0]] || 'necessary';
		var d   = t(OCD_DESC[rec[0]] || OCD_DESC[0]);
		if (rec[1]) { d += ' — ' + rec[1]; } // append platform, e.g. "Marketing cookie — Pinterest"
		return { cat: cat, name: name, actual: name, dur: normRet(rec[2]), desc: d };
	}

	// Returns a resolved table entry for a cookie name, or null.
	function ocdLookup(name) {
		if (!OCD) { return null; }
		if (OCD.exact && OCD.exact[name]) { return ocdEntry(OCD.exact[name], name); }
		if (OCD.wild) {
			for (var i = 0; i < OCD.wild.length; i++) {
				var w = OCD.wild[i];
				if (name.indexOf(w[0]) === 0) {
					return ocdEntry([ w[1], w[2], w[3] ], name);
				}
			}
		}
		return null;
	}

	// Lazy-load the database, then run cb (always called, even on failure).
	function ensureOCD(cb) {
		if (ocdState === 'done' || ocdState === 'failed') { cb(); return; }
		if (ocdState === 'loading') { cb(); return; }
		if (!settings.ocdUrl || typeof window.fetch !== 'function') { ocdState = 'failed'; cb(); return; }
		ocdState = 'loading';
		window.fetch(settings.ocdUrl)
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) { OCD = data; ocdState = data ? 'done' : 'failed'; cb(); })
			.catch(function () { ocdState = 'failed'; cb(); });
	}

	// ── Cookie pattern helpers ───────────────────────────────────────────────────

	function exact(s)  { return function (n) { return n === s; }; }
	function prefix(s) { return function (n) { return n.indexOf(s) === 0; }; }
	function rx(r)     { return function (n) { return r.test(n); }; }

	// ── Cookie knowledge base ────────────────────────────────────────────────────
	// Each entry: { test, cat, name, dur (token), desc ({lv,ru,en}), requires? }
	// 'requires' is a key in settings.activeFeatures — entry is skipped if feature absent.

	var COOKIE_DB = [
		// Necessary
		{ test: exact('coma_consent'),              cat: 'necessary', name: 'coma_consent',              dur: 'consent',      desc: { lv: 'Saglabā jūsu sīkdatņu preferences',     ru: 'Сохраняет ваши настройки cookie',           en: 'Stores your cookie preferences', lt: 'Saugo jūsų slapukų nustatymus', et: 'Salvestab teie küpsiste eelistused' } },
		{ test: prefix('wordpress_'),               cat: 'necessary', name: 'wordpress_*',               dur: 'session', desc: { lv: 'WordPress autentifikācija un drošība',  ru: 'Аутентификация и безопасность WordPress',   en: 'WordPress authentication and security', lt: 'WordPress autentifikacija ir sauga', et: 'WordPressi autentimine ja turvalisus' } },
		{ test: prefix('wp-settings'),              cat: 'necessary', name: 'wp-settings*',              dur: 'y1',      desc: { lv: 'WordPress saskarnes iestatījumi',       ru: 'Настройки интерфейса WordPress',            en: 'WordPress interface settings', lt: 'WordPress sąsajos nustatymai', et: 'WordPressi liidese seaded' } },
		{ test: prefix('wp-wpml_'),                 cat: 'necessary', name: 'wp-wpml_*',                  dur: 'session', desc: { lv: 'WPML — atceras izvēlēto valodu',        ru: 'WPML — запоминает выбранный язык',           en: 'WPML — remembers the selected language', lt: 'WPML — įsimena pasirinktą kalbą', et: 'WPML — jätab meelde valitud keele' } },
		{ test: prefix('_icl_'),                    cat: 'necessary', name: '_icl_*',                    dur: 'session', desc: { lv: 'WPML — valodas pārvaldība',             ru: 'WPML — управление языком',                  en: 'WPML — language management', lt: 'WPML — kalbos valdymas', et: 'WPML — keele haldus' } },
		{ test: exact('PHPSESSID'),                 cat: 'necessary', name: 'PHPSESSID',                 dur: 'session', desc: { lv: 'PHP sesijas identifikācija',            ru: 'Идентификация сессии PHP',                  en: 'PHP session identifier', lt: 'PHP sesijos identifikatorius', et: 'PHP seansi identifikaator' } },
		{ test: prefix('rc::'),                     cat: 'necessary', name: 'rc::*',                     dur: 'persistent', desc: { lv: 'Google reCAPTCHA — krāpšanas novēršana', ru: 'Google reCAPTCHA — защита от мошенничества', en: 'Google reCAPTCHA — fraud prevention', lt: 'Google reCAPTCHA — sukčiavimo prevencija', et: 'Google reCAPTCHA — pettuste ennetamine' } },
		{ test: prefix('comment_author'),           cat: 'necessary', name: 'comment_author_*',          dur: 'y1',      desc: { lv: 'WordPress — atceras komentāru autoru',  ru: 'WordPress — запоминает автора комментария', en: 'WordPress — remembers the comment author', lt: 'WordPress — įsimena komentaro autorių', et: 'WordPress — jätab meelde kommentaari autori' } },
		{ test: exact('woocommerce_cart_hash'),     cat: 'necessary', name: 'woocommerce_cart_hash',     dur: 'session', desc: { lv: 'WooCommerce — groza identifikācija',    ru: 'WooCommerce — идентификация корзины',       en: 'WooCommerce — cart identifier', lt: 'WooCommerce — krepšelio identifikatorius', et: 'WooCommerce — ostukorvi identifikaator' }, requires: 'hasWoocommerce' },
		{ test: exact('woocommerce_items_in_cart'), cat: 'necessary', name: 'woocommerce_items_in_cart', dur: 'session', desc: { lv: 'WooCommerce — produktu skaits grozā',   ru: 'WooCommerce — количество товаров в корзине', en: 'WooCommerce — number of items in cart', lt: 'WooCommerce — prekių skaičius krepšelyje', et: 'WooCommerce — toodete arv ostukorvis' }, requires: 'hasWoocommerce' },
		{ test: exact('wc_session_cookie'),         cat: 'necessary', name: 'wc_session_cookie',         dur: 'day2',    desc: { lv: 'WooCommerce — sesijas dati',            ru: 'WooCommerce — данные сессии',               en: 'WooCommerce — session data', lt: 'WooCommerce — sesijos duomenys', et: 'WooCommerce — seansi andmed' }, requires: 'hasWoocommerce' },

		// Analytics
		{ test: exact('_ga'),                       cat: 'analytics', name: '_ga',                       dur: 'y2',      desc: { lv: 'Google Analytics — apmeklētāja identifikācija', ru: 'Google Analytics — идентификация посетителя', en: 'Google Analytics — visitor identifier', lt: 'Google Analytics — lankytojo identifikatorius', et: 'Google Analytics — külastaja identifikaator' } },
		{ test: prefix('_ga_'),                     cat: 'analytics', name: '_ga_*',                     dur: 'y2',      desc: { lv: 'Google Analytics — sesijas dati',       ru: 'Google Analytics — данные сессии',          en: 'Google Analytics — session data', lt: 'Google Analytics — sesijos duomenys', et: 'Google Analytics — seansi andmed' } },
		{ test: exact('_gid'),                      cat: 'analytics', name: '_gid',                      dur: 'day1',    desc: { lv: 'Google Analytics — lietotāja atšķiršana', ru: 'Google Analytics — различение пользователей', en: 'Google Analytics — user distinction', lt: 'Google Analytics — naudotojų atskyrimas', et: 'Google Analytics — kasutajate eristamine' } },
		{ test: prefix('_gat'),                     cat: 'analytics', name: '_gat*',                     dur: 'min1',    desc: { lv: 'Google Analytics — pieprasījumu ierobežošana', ru: 'Google Analytics — ограничение запросов', en: 'Google Analytics — request throttling', lt: 'Google Analytics — užklausų ribojimas', et: 'Google Analytics — päringute piiramine' } },
		{ test: exact('__utma'),                    cat: 'analytics', name: '__utma',                    dur: 'y2',      desc: { lv: 'Google Analytics — apmeklējumu izsekošana', ru: 'Google Analytics — отслеживание визитов', en: 'Google Analytics — visit tracking', lt: 'Google Analytics — apsilankymų sekimas', et: 'Google Analytics — külastuste jälgimine' } },
		{ test: exact('__utmb'),                    cat: 'analytics', name: '__utmb',                    dur: 'min30',   desc: { lv: 'Google Analytics — sesijas ilgums',     ru: 'Google Analytics — длительность сессии',    en: 'Google Analytics — session duration', lt: 'Google Analytics — sesijos trukmė', et: 'Google Analytics — seansi kestus' } },
		{ test: exact('__utmc'),                    cat: 'analytics', name: '__utmc',                    dur: 'session', desc: { lv: 'Google Analytics — sesija',             ru: 'Google Analytics — сессия',                 en: 'Google Analytics — session', lt: 'Google Analytics — sesija', et: 'Google Analytics — seanss' } },
		{ test: exact('__utmz'),                    cat: 'analytics', name: '__utmz',                    dur: 'mo6',     desc: { lv: 'Google Analytics — trafika avots',      ru: 'Google Analytics — источник трафика',       en: 'Google Analytics — traffic source', lt: 'Google Analytics — srauto šaltinis', et: 'Google Analytics — liikluse allikas' } },

		// Marketing
		{ test: prefix('sbjs_'),                    cat: 'marketing', name: 'sbjs_*',                    dur: 'mo6',     desc: { lv: 'WooCommerce — pasūtījuma avota atribūcija (Sourcebuster)', ru: 'WooCommerce — атрибуция источника заказа (Sourcebuster)', en: 'WooCommerce — order source attribution (Sourcebuster)', lt: 'WooCommerce — užsakymo šaltinio priskyrimas (Sourcebuster)', et: 'WooCommerce — tellimuse allika omistamine (Sourcebuster)' } },
		{ test: exact('_fbp'),                      cat: 'marketing', name: '_fbp',                      dur: 'mo3',     desc: { lv: 'Facebook Pixel — apmeklētāju izsekošana', ru: 'Facebook Pixel — отслеживание посетителей', en: 'Facebook Pixel — visitor tracking', lt: 'Facebook Pixel — lankytojų sekimas', et: 'Facebook Pixel — külastajate jälgimine' } },
		{ test: exact('_fbc'),                      cat: 'marketing', name: '_fbc',                      dur: 'mo3',     desc: { lv: 'Facebook — klikšķu izsekošana',         ru: 'Facebook — отслеживание кликов',            en: 'Facebook — click tracking', lt: 'Facebook — paspaudimų sekimas', et: 'Facebook — klikkide jälgimine' } },
		{ test: exact('fr'),                        cat: 'marketing', name: 'fr',                        dur: 'mo3',     desc: { lv: 'Facebook — reklāmu izsekošana',         ru: 'Facebook — отслеживание рекламы',           en: 'Facebook — ad tracking', lt: 'Facebook — reklamos sekimas', et: 'Facebook — reklaamide jälgimine' } },
		{ test: prefix('_gcl_'),                    cat: 'marketing', name: '_gcl_*',                    dur: 'mo3',     desc: { lv: 'Google Ads — konversijas izsekošana',   ru: 'Google Ads — отслеживание конверсий',       en: 'Google Ads — conversion tracking', lt: 'Google Ads — konversijų sekimas', et: 'Google Ads — konversioonide jälgimine' } },
		{ test: exact('IDE'),                       cat: 'marketing', name: 'IDE',                       dur: 'y1',      desc: { lv: 'Google Display — reklāmu personalizācija', ru: 'Google Display — персонализация рекламы', en: 'Google Display — ad personalisation', lt: 'Google Display — reklamos personalizavimas', et: 'Google Display — reklaamide isikupärastamine' } },
		{ test: exact('NID'),                       cat: 'marketing', name: 'NID',                       dur: 'mo6',     desc: { lv: 'Google — reklāmu preferences',          ru: 'Google — настройки рекламы',                en: 'Google — ad preferences', lt: 'Google — reklamos nustatymai', et: 'Google — reklaamieelistused' } },
		{ test: prefix('_tt_'),                     cat: 'marketing', name: '_tt_*',                     dur: 'y1',      desc: { lv: 'TikTok Pixel — reklāmu izsekošana',     ru: 'TikTok Pixel — отслеживание рекламы',       en: 'TikTok Pixel — ad tracking', lt: 'TikTok Pixel — reklamos sekimas', et: 'TikTok Pixel — reklaamide jälgimine' } },
		{ test: prefix('_ttp'),                     cat: 'marketing', name: '_ttp',                      dur: 'y1',      desc: { lv: 'TikTok — reklāmu izsekošana',           ru: 'TikTok — отслеживание рекламы',             en: 'TikTok — ad tracking', lt: 'TikTok — reklamos sekimas', et: 'TikTok — reklaamide jälgimine' } },
		{ test: prefix('li_'),                      cat: 'marketing', name: 'li_*',                      dur: 'mo6',     desc: { lv: 'LinkedIn Insight — reklāmu izsekošana', ru: 'LinkedIn Insight — отслеживание рекламы',   en: 'LinkedIn Insight — ad tracking', lt: 'LinkedIn Insight — reklamos sekimas', et: 'LinkedIn Insight — reklaamide jälgimine' } },
	];

	// Script URL/content patterns → which DB entries to guarantee even if cookie not yet set
	var SCRIPT_SIGNALS = [
		{
			detect: /googletagmanager\.com|googletagservices|gtag\/js|google-analytics\.com/,
			ensure: [ exact('_ga'), prefix('_ga_'), exact('_gid') ]
		},
		{
			detect: /connect\.facebook\.net|fbevents\.js/,
			ensure: [ exact('_fbp'), exact('_fbc') ]
		},
		{
			detect: /gstatic\.com\/recaptcha|recaptcha\/releases/,
			ensure: [ prefix('rc::') ]
		},
		{
			detect: /googleadservices\.com|googlesyndication|doubleclick\.net/,
			ensure: [ prefix('_gcl_'), exact('IDE') ]
		},
		{
			detect: /analytics\.tiktok\.com|tiktok\.com\/i\/pixel/,
			ensure: [ prefix('_tt_'), prefix('_ttp') ]
		},
		{
			detect: /snap\.licdn\.com|linkedin\.com\/insight/,
			ensure: [ prefix('li_') ]
		},
	];

	// ── Tracker scanner ──────────────────────────────────────────────────────────

	function featureActive(key) {
		return !!(settings.activeFeatures && settings.activeFeatures[key]);
	}

	function lookupCookie(name) {
		for (var i = 0; i < COOKIE_DB.length; i++) {
			var entry = COOKIE_DB[i];
			if (entry.requires && !featureActive(entry.requires)) continue;
			if (entry.test(name)) return entry;
		}
		return null;
	}

	function scanTrackers() {
		var cats = { necessary: [], analytics: [], marketing: [] };
		var seen = {}; // track by display name to avoid duplicates

		function addEntry(entry, actualName) {
			var displayName = entry.name;
			if (seen[displayName]) return;
			seen[displayName] = true;
			if (cats[entry.cat]) {
				cats[entry.cat].push({ name: displayName, actual: actualName, dur: dur(entry.dur), desc: t(entry.desc) });
			}
		}

		// 1. Scan actual cookies in document.cookie
		var actualCookies = document.cookie.split(';').map(function (c) {
			return c.trim().split('=')[0].trim();
		}).filter(Boolean);

		actualCookies.forEach(function (name) {
			// 1) Curated DB (priority — localized descriptions, hand-tuned).
			var entry = lookupCookie(name);
			if (entry) {
				addEntry(entry, name);
				return;
			}
			// 2) Open Cookie Database (broad coverage, loaded on modal open).
			var o = ocdLookup(name);
			if (o) {
				if (!seen[name]) {
					seen[name] = true;
					if (cats[o.cat]) {
						cats[o.cat].push({ name: name, actual: name, dur: o.dur, desc: o.desc });
					}
				}
				return;
			}
			// 3) Still unknown — disclose under "necessary".
			if (!seen[name]) {
				seen[name] = true;
				cats.necessary.push({ name: name, actual: name, dur: dur('unknown'), desc: MSG.unclassified || 'Unclassified cookie' });
			}
		});

		// 2. Script-signal detection — add expected cookies for loaded trackers
		var scriptSrc = Array.prototype.slice.call(document.querySelectorAll('script[src]'))
			.map(function (s) { return s.src; }).join(' ');
		var inlineSrc = Array.prototype.slice.call(document.querySelectorAll('script:not([src])'))
			.map(function (s) { return s.textContent; }).join(' ');
		var allSrc = scriptSrc + ' ' + inlineSrc;

		SCRIPT_SIGNALS.forEach(function (signal) {
			if (!signal.detect.test(allSrc)) return;
			signal.ensure.forEach(function (testFn) {
				for (var i = 0; i < COOKIE_DB.length; i++) {
					var entry = COOKIE_DB[i];
					if (entry.requires && !featureActive(entry.requires)) continue;
					// Check if this DB entry's test matches the ensure testFn
					// by testing the entry's canonical name against testFn
					if (testFn(entry.name.replace('*', ''))) {
						addEntry(entry, entry.name);
						break;
					}
				}
			});
		});

		// 3. Always include coma_consent in necessary
		var consent = lookupCookie('coma_consent');
		if (consent && !seen['coma_consent']) addEntry(consent, 'coma_consent');

		return cats;
	}

	// ── Render cookie tables ─────────────────────────────────────────────────────

	function renderCookieTables() {
		var cats = scanTrackers();
		['necessary', 'analytics', 'marketing'].forEach(function (cat) {
			var tbody = document.getElementById('ccc-tbody-' + cat);
			if (!tbody) return;
			var cookies = cats[cat] || [];
			if (!cookies.length) {
				tbody.innerHTML = '<tr><td colspan="3" style="color:var(--ccc-muted);font-style:italic">' + esc(MSG.none || 'No cookies detected') + '</td></tr>';
				return;
			}
			tbody.innerHTML = cookies.map(function (c) {
				return '<tr><td><code>' + esc(c.name) + '</code></td><td>' + esc(c.dur) + '</td><td>' + esc(c.desc) + '</td></tr>';
			}).join('');
		});
	}

	function esc(s) {
		return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	// ── Cookie helpers ───────────────────────────────────────────────────────────

	function readConsent() {
		var m = document.cookie.match(/(?:^|;\s*)coma_consent=([^;]+)/);
		if (!m) return null;
		try { return JSON.parse(decodeURIComponent(m[1])); } catch (e) { return null; }
	}

	function writeConsent(obj) {
		var prev = readConsent();
		obj.id   = obj.id || (prev && prev.id) || uuid(); // stable id across updates
		obj.ts   = Date.now();
		obj.v    = CONSENT_VER;
		var val     = encodeURIComponent(JSON.stringify(obj));
		var expires = new Date(Date.now() + COOKIE_EXPIRY * 864e5).toUTCString();
		document.cookie = COOKIE_NAME + '=' + val +
			'; expires=' + expires +
			'; path=/; SameSite=Lax; Secure';
	}

	function uuid() {
		if (window.crypto && crypto.randomUUID) { try { return crypto.randomUUID(); } catch (e) {} }
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = Math.random() * 16 | 0;
			return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
		});
	}

	// ── Google Consent Mode update ───────────────────────────────────────────────

	function pushGCM(consent) {
		if (typeof gtag !== 'function') return;
		gtag('consent', 'update', {
			'analytics_storage':     consent.analytics ? 'granted' : 'denied',
			'ad_storage':            consent.marketing ? 'granted' : 'denied',
			'ad_user_data':          consent.marketing ? 'granted' : 'denied',
			'ad_personalization':    consent.marketing ? 'granted' : 'denied',
			'functionality_storage': consent.functional ? 'granted' : 'denied',
			'security_storage':      'granted'
		});
		if (window.dataLayer) {
			window.dataLayer.push({
				event:             'cookie_consent_update',
				consent_analytics: consent.analytics,
				consent_marketing: consent.marketing,
			});
		}
	}

	// ── Tracker un-blocking ──────────────────────────────────────────────────────
	// Re-activate scripts/iframes the server neutralised (data-ccc-block) for any
	// category the visitor has now granted. A compound category ("analytics+marketing")
	// needs every part granted.

	function granted(consent, cat) {
		var parts = String(cat || '').split('+');
		for (var i = 0; i < parts.length; i++) {
			if (!consent[parts[i]]) return false;
		}
		return parts.length > 0;
	}

	function unblockScripts(consent) {
		if (!settings.blockTrackers) return;
		var nodes = document.querySelectorAll('script[type="text/plain"][data-ccc-block]');
		for (var i = 0; i < nodes.length; i++) {
			var old = nodes[i];
			if (!granted(consent, old.getAttribute('data-ccc-block'))) continue;
			var s = document.createElement('script');
			for (var j = 0; j < old.attributes.length; j++) {
				var a = old.attributes[j];
				if (a.name === 'type' || a.name === 'data-ccc-block') continue;
				if (a.name === 'data-ccc-src') { s.src = a.value; continue; }
				s.setAttribute(a.name, a.value);
			}
			if (s.src) {
				// Injected external scripts are async by default; keep document order
				// unless the original tag asked for async itself.
				if (!old.hasAttribute('async')) { s.async = false; }
			} else {
				s.textContent = old.textContent;
			}
			old.parentNode.replaceChild(s, old);
		}
		var frames = document.querySelectorAll('iframe[data-ccc-block][data-ccc-src]');
		for (var k = 0; k < frames.length; k++) {
			var f = frames[k];
			if (!granted(consent, f.getAttribute('data-ccc-block'))) continue;
			f.setAttribute('src', f.getAttribute('data-ccc-src'));
			f.removeAttribute('data-ccc-src');
			f.removeAttribute('data-ccc-block');
		}
	}

	// ── Consent record (server-side log) ─────────────────────────────────────────

	function logConsent(action, consent) {
		if (!settings.logUrl || typeof window.fetch !== 'function') return;
		try {
			window.fetch(settings.logUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				keepalive: true,
				body: JSON.stringify({
					id:      consent.id,
					action:  action,
					version: CONSENT_VER,
					lang:    LANG,
					choices: {
						necessary:  true,
						functional: !!consent.functional,
						analytics:  !!consent.analytics,
						marketing:  !!consent.marketing
					}
				})
			}).catch(function () {});
		} catch (e) {}
	}

	// Persist + propagate a choice everywhere (cookie, GCM, scripts, log).
	function applyConsent(consent, action) {
		writeConsent(consent);
		pushGCM(consent);
		unblockScripts(consent);
		if (action) { logConsent(action, consent); }
	}

	// ── Banner / modal refs ──────────────────────────────────────────────────────

	var banner  = document.getElementById('ccc-banner');
	var modal   = document.getElementById('ccc-modal');
	var overlay = document.getElementById('ccc-overlay');
	var revisit = document.getElementById('ccc-revisit');

	// Line the banner content up with the theme's content container (settings.alignSelector).
	function alignBanner() {
		var inner = banner && banner.querySelector('.ccc-banner__inner');
		var box   = settings.alignSelector && document.querySelector(settings.alignSelector);
		if (!inner || !box || banner.hidden) return;
		var r     = box.getBoundingClientRect();
		var cs    = window.getComputedStyle(box);
		var vw    = document.documentElement.clientWidth;
		var left  = r.left + (parseFloat(cs.paddingLeft) || 0);
		var right = vw - r.right + (parseFloat(cs.paddingRight) || 0);
		if (r.width < 200 || left < 12 || right < 12) {
			inner.style.removeProperty('max-width');
			inner.style.removeProperty('padding');
			return;
		}
		inner.style.setProperty('max-width', 'none', 'important');
		inner.style.setProperty('padding', '0 ' + Math.round(right) + 'px 0 ' + Math.round(left) + 'px', 'important');
	}

	var alignTimer;
	window.addEventListener('resize', function () {
		clearTimeout(alignTimer);
		alignTimer = setTimeout(alignBanner, 100);
	});

	// Publish the banner's height as --ccc-banner-h on <html> (0px when hidden),
	// so a site's own fixed bottom bar can sit above the banner instead of under it:
	//   .my-bar { bottom: var(--ccc-banner-h, 0px); }
	function syncBannerHeight() {
		var h = (banner && !banner.hidden) ? Math.round(banner.getBoundingClientRect().height) : 0;
		document.documentElement.style.setProperty('--ccc-banner-h', h + 'px');
	}
	window.addEventListener('resize', syncBannerHeight);

	function showBanner() {
		if (banner) banner.hidden = false;
		if (revisit) revisit.hidden = true;
		alignBanner();
		syncBannerHeight();
	}

	function hideBanner() {
		if (banner) banner.hidden = true;
		syncBannerHeight();
		if (revisit && settings.revisitButton) revisit.hidden = false;
	}

	function openModal() {
		if (!modal) return;
		syncToggles();
		modal.hidden = false;
		document.body.style.overflow = 'hidden';
		var closeBtn = document.getElementById('ccc-modal-close');
		if (closeBtn) closeBtn.focus();
		// Load the Open Cookie Database on first open, then refresh the tables so
		// previously-unknown cookies get classified.
		ensureOCD(function () { renderCookieTables(); });
	}

	function closeModal() {
		if (!modal) return;
		modal.hidden = true;
		document.body.style.overflow = '';
	}

	// ── Toggle sync ──────────────────────────────────────────────────────────────

	function syncToggles() {
		var consent = readConsent();
		['analytics', 'marketing', 'functional'].forEach(function (cat) {
			var el = document.getElementById('ccc-cat-' + cat);
			if (!el) return;
			el.checked = consent ? !!consent[cat] : false;
		});
	}

	function getToggleValues() {
		var result = { necessary: true, functional: false, analytics: false, marketing: false };
		['analytics', 'marketing', 'functional'].forEach(function (cat) {
			var el = document.getElementById('ccc-cat-' + cat);
			if (el) result[cat] = el.checked;
		});
		return result;
	}

	// ── Accept / Reject / Save ───────────────────────────────────────────────────

	function acceptAll() {
		var consent = { necessary: true, functional: true, analytics: settings.enableAnalytics !== false, marketing: settings.enableMarketing !== false };
		applyConsent(consent, 'accept');
		hideBanner();
		closeModal();
	}

	function rejectAll() {
		var consent = { necessary: true, functional: false, analytics: false, marketing: false };
		applyConsent(consent, 'reject');
		hideBanner();
		closeModal();
	}

	function savePrefs() {
		var consent = getToggleValues();
		consent.necessary = true;
		applyConsent(consent, 'save');
		hideBanner();
		closeModal();
	}

	// ── Category expanders ───────────────────────────────────────────────────────

	function initExpanders() {
		document.querySelectorAll('.ccc-cat__expander').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var expanded = this.getAttribute('aria-expanded') === 'true';
				var target   = document.getElementById(this.getAttribute('aria-controls'));
				this.setAttribute('aria-expanded', String(!expanded));
				if (target) target.hidden = expanded;
				this.classList.toggle('ccc-cat__expander--open', !expanded);
			});
		});
	}

	// ── Keyboard accessibility ───────────────────────────────────────────────────

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
	});

	// ── Wire up buttons ──────────────────────────────────────────────────────────

	function bindButton(id, fn) {
		var el = document.getElementById(id);
		if (el) el.addEventListener('click', fn);
	}

	function init() {
		bindButton('ccc-btn-accept',    acceptAll);
		bindButton('ccc-btn-reject',    rejectAll);
		bindButton('ccc-btn-customize', openModal);
		bindButton('ccc-btn-accept-all', acceptAll);
		bindButton('ccc-btn-reject-all', rejectAll);
		bindButton('ccc-btn-save',       savePrefs);
		bindButton('ccc-modal-close',    closeModal);
		bindButton('ccc-revisit', function () { openModal(); });

		if (overlay) overlay.addEventListener('click', closeModal);

		initExpanders();
		renderCookieTables();

		var existing = readConsent();
		if (existing && existing.v !== CONSENT_VER) {
			existing = null; // consent policy changed → ask again
		}
		if (!existing) {
			setTimeout(showBanner, 300);
		} else {
			pushGCM(existing);
			unblockScripts(existing);
			if (revisit && settings.revisitButton) revisit.hidden = false;
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

<?php
/**
 * Central translation table for Coma Cookie Consent.
 *
 * Supported languages: lv, ru, en, lt, et.
 * - "chrome"  : UI strings rendered by PHP (buttons, labels, category meta).
 * - "content" : default banner title/description shown until overridden in admin.
 *
 * To add a language: add a key here AND in frontend/js/consent.js (DUR / cookie desc objects).
 *
 * @return array<string,array<string,mixed>>
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ccc_strings() {
	return array(

		'lv' => array(
			'label'   => 'Latviešu',
			'chrome'  => array(
				'banner_aria'        => 'Sīkdatņu paziņojums',
				'customize'          => 'Pielāgot',
				'reject'             => 'Noraidīt',
				'accept'             => 'Pieņemt visas',
				'modal_title'        => 'Sīkdatņu preferences',
				'close'              => 'Aizvērt',
				'reject_all'         => 'Noraidīt visas',
				'accept_all'         => 'Pieņemt visas',
				'save'               => 'Saglabāt preferences',
				'always_on'          => 'Vienmēr aktīvs',
				'th_cookie'          => 'Sīkdatne',
				'th_duration'        => 'Ilgums',
				'th_desc'            => 'Apraksts',
				'revisit_aria'       => 'Sīkdatņu iestatījumi',
				'learn_more'         => 'Uzzināt vairāk',
				'cat_necessary'      => 'Nepieciešamās',
				'cat_analytics'      => 'Analītiskie',
				'cat_marketing'      => 'Mārketinga',
				'cat_necessary_desc' => 'Šīs sīkdatnes ir nepieciešamas vietnes pamata funkcijām un nevar tikt atspējotas.',
				'cat_analytics_desc' => 'Palīdz saprast, kā apmeklētāji lieto vietni, apkopojot anonīmu statistiku.',
				'cat_marketing_desc' => 'Tiek izmantotas, lai rādītu personalizētas reklāmas un sekotu to efektivitātei.',
			),
			'content' => array(
				'banner_title'       => 'Šī vietne izmanto sīkdatnes',
				'banner_description' => 'Mēs izmantojam sīkdatnes, lai uzlabotu jūsu pārlūkošanas pieredzi, analizētu vietnes apmeklētību un personalizētu saturu. Lūdzu, izvēlieties, kuras sīkdatnes atļaut.',
			),
		),

		'ru' => array(
			'label'   => 'Русский',
			'chrome'  => array(
				'banner_aria'        => 'Уведомление о файлах cookie',
				'customize'          => 'Настроить',
				'reject'             => 'Отклонить',
				'accept'             => 'Принять все',
				'modal_title'        => 'Настройки файлов cookie',
				'close'              => 'Закрыть',
				'reject_all'         => 'Отклонить все',
				'accept_all'         => 'Принять все',
				'save'               => 'Сохранить настройки',
				'always_on'          => 'Всегда активны',
				'th_cookie'          => 'Файл cookie',
				'th_duration'        => 'Срок',
				'th_desc'            => 'Описание',
				'revisit_aria'       => 'Настройки файлов cookie',
				'learn_more'         => 'Узнать больше',
				'cat_necessary'      => 'Необходимые',
				'cat_analytics'      => 'Аналитические',
				'cat_marketing'      => 'Маркетинговые',
				'cat_necessary_desc' => 'Эти файлы cookie необходимы для базовых функций сайта и не могут быть отключены.',
				'cat_analytics_desc' => 'Помогают понять, как посетители используют сайт, собирая анонимную статистику.',
				'cat_marketing_desc' => 'Используются для показа персонализированной рекламы и оценки её эффективности.',
			),
			'content' => array(
				'banner_title'       => 'Этот сайт использует файлы cookie',
				'banner_description' => 'Мы используем файлы cookie, чтобы улучшить ваш опыт просмотра, анализировать посещаемость сайта и персонализировать контент. Пожалуйста, выберите, какие файлы cookie разрешить.',
			),
		),

		'en' => array(
			'label'   => 'English',
			'chrome'  => array(
				'banner_aria'        => 'Cookie notice',
				'customize'          => 'Customise',
				'reject'             => 'Reject',
				'accept'             => 'Accept all',
				'modal_title'        => 'Cookie preferences',
				'close'              => 'Close',
				'reject_all'         => 'Reject all',
				'accept_all'         => 'Accept all',
				'save'               => 'Save preferences',
				'always_on'          => 'Always active',
				'th_cookie'          => 'Cookie',
				'th_duration'        => 'Duration',
				'th_desc'            => 'Description',
				'revisit_aria'       => 'Cookie settings',
				'learn_more'         => 'Learn more',
				'cat_necessary'      => 'Necessary',
				'cat_analytics'      => 'Analytics',
				'cat_marketing'      => 'Marketing',
				'cat_necessary_desc' => 'These cookies are required for the basic functionality of the site and cannot be disabled.',
				'cat_analytics_desc' => 'Help us understand how visitors use the site by collecting anonymous statistics.',
				'cat_marketing_desc' => 'Used to display personalised ads and track their effectiveness.',
			),
			'content' => array(
				'banner_title'       => 'This website uses cookies',
				'banner_description' => 'We use cookies to improve your browsing experience, analyse site traffic and personalise content. Please choose which cookies to allow.',
			),
		),

		'lt' => array(
			'label'   => 'Lietuvių',
			'chrome'  => array(
				'banner_aria'        => 'Pranešimas apie slapukus',
				'customize'          => 'Tinkinti',
				'reject'             => 'Atmesti',
				'accept'             => 'Priimti visus',
				'modal_title'        => 'Slapukų nustatymai',
				'close'              => 'Uždaryti',
				'reject_all'         => 'Atmesti visus',
				'accept_all'         => 'Priimti visus',
				'save'               => 'Išsaugoti nustatymus',
				'always_on'          => 'Visada aktyvūs',
				'th_cookie'          => 'Slapukas',
				'th_duration'        => 'Trukmė',
				'th_desc'            => 'Aprašymas',
				'revisit_aria'       => 'Slapukų nustatymai',
				'learn_more'         => 'Sužinoti daugiau',
				'cat_necessary'      => 'Būtinieji',
				'cat_analytics'      => 'Analitiniai',
				'cat_marketing'      => 'Rinkodaros',
				'cat_necessary_desc' => 'Šie slapukai būtini pagrindinėms svetainės funkcijoms ir negali būti išjungti.',
				'cat_analytics_desc' => 'Padeda suprasti, kaip lankytojai naudojasi svetaine, renkant anoniminę statistiką.',
				'cat_marketing_desc' => 'Naudojami asmeninei reklamai rodyti ir jos veiksmingumui stebėti.',
			),
			'content' => array(
				'banner_title'       => 'Ši svetainė naudoja slapukus',
				'banner_description' => 'Naudojame slapukus, kad pagerintume naršymo patirtį, analizuotume svetainės lankomumą ir pritaikytume turinį. Pasirinkite, kuriuos slapukus leidžiate naudoti.',
			),
		),

		'et' => array(
			'label'   => 'Eesti',
			'chrome'  => array(
				'banner_aria'        => 'Küpsiste teade',
				'customize'          => 'Kohanda',
				'reject'             => 'Keeldu',
				'accept'             => 'Nõustu kõigiga',
				'modal_title'        => 'Küpsiste eelistused',
				'close'              => 'Sulge',
				'reject_all'         => 'Keeldu kõigist',
				'accept_all'         => 'Nõustu kõigiga',
				'save'               => 'Salvesta eelistused',
				'always_on'          => 'Alati aktiivne',
				'th_cookie'          => 'Küpsis',
				'th_duration'        => 'Kestus',
				'th_desc'            => 'Kirjeldus',
				'revisit_aria'       => 'Küpsiste seaded',
				'learn_more'         => 'Loe lähemalt',
				'cat_necessary'      => 'Vajalikud',
				'cat_analytics'      => 'Analüütilised',
				'cat_marketing'      => 'Turunduslikud',
				'cat_necessary_desc' => 'Need küpsised on vajalikud veebilehe põhifunktsioonide toimimiseks ja neid ei saa välja lülitada.',
				'cat_analytics_desc' => 'Aitavad mõista, kuidas külastajad veebilehte kasutavad, kogudes anonüümset statistikat.',
				'cat_marketing_desc' => 'Kasutatakse isikupärastatud reklaamide kuvamiseks ja nende tõhususe jälgimiseks.',
			),
			'content' => array(
				'banner_title'       => 'See veebileht kasutab küpsiseid',
				'banner_description' => 'Kasutame küpsiseid, et parandada sirvimiskogemust, analüüsida veebilehe külastatavust ja isikupärastada sisu. Palun valige, milliseid küpsiseid lubate.',
			),
		),

	);
}

/**
 * Supported language slugs, in display order.
 *
 * @return string[]
 */
function ccc_languages() {
	return array( 'lv', 'ru', 'en', 'lt', 'et' );
}

/**
 * Supported languages the site actually serves, in display order.
 *
 * Multilingual sites: the Polylang / WPML languages (locale first, then code —
 * same matching as the frontend) that the plugin supports. Single-language
 * sites: just the configured default language, since that is the only one the
 * banner can ever show there.
 *
 * @return string[]
 */
function ccc_site_languages() {
	$codes = array();

	if ( function_exists( 'pll_languages_list' ) ) {
		$codes = array_merge(
			(array) pll_languages_list( array( 'fields' => 'locale' ) ),
			(array) pll_languages_list( array( 'fields' => 'slug' ) )
		);
	} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		$wpml = apply_filters( 'wpml_active_languages', null, 'skip_missing=0' );
		foreach ( (array) $wpml as $code => $lang ) {
			$codes[] = $lang['default_locale'] ?? '';
			$codes[] = $code;
		}
	}

	$found = array();
	foreach ( $codes as $code ) {
		$found[] = strtolower( substr( (string) $code, 0, 2 ) );
	}
	$langs = array_values( array_intersect( ccc_languages(), $found ) );

	if ( ! $langs ) {
		$opts    = get_option( CCC_OPTION, array() );
		$default = $opts['default_lang'] ?? 'lv';
		$langs   = array( in_array( $default, ccc_languages(), true ) ? $default : 'lv' );
	}

	return $langs;
}

/**
 * English names of the supported languages, for the admin screen.
 *
 * @return string[]
 */
function ccc_language_names() {
	return array(
		'lv' => 'Latvian',
		'ru' => 'Russian',
		'en' => 'English',
		'lt' => 'Lithuanian',
		'et' => 'Estonian',
	);
}

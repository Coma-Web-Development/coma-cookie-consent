<?php
/**
 * Central translation table for Coma Cookie Consent.
 *
 * Supported languages: lv, ru, en.
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

	);
}

/**
 * Supported language slugs, in display order.
 *
 * @return string[]
 */
function ccc_languages() {
	return array( 'lv', 'ru', 'en' );
}

<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function apply_filters(string $hook, mixed $value): mixed {
	return $value;
}

function esc_attr(string $value): string {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/../frontend/class-frontend.php';

function assert_contains(string $needle, string $haystack, string $message): void {
	if (strpos($haystack, $needle) === false) {
		fwrite(STDERR, "FAIL: {$message}\n");
		exit(1);
	}
}

function assert_not_contains(string $needle, string $haystack, string $message): void {
	if (strpos($haystack, $needle) !== false) {
		fwrite(STDERR, "FAIL: {$message}\n");
		exit(1);
	}
}

$reflection = new ReflectionClass('CCC_Frontend');
$frontend   = $reflection->newInstanceWithoutConstructor();

$iframe_blocker = $reflection->getMethod('block_iframes');
$iframe_html = '<iframe src="about:blank" data-src="https://www.facebook.com/plugins/page.php" loading="lazy"></iframe>';
$iframe_out  = $iframe_blocker->invoke($frontend, $iframe_html);

assert_contains('data-ccc-src="about:blank"', $iframe_out, 'the real iframe src must be blocked');
assert_contains('data-src="https://www.facebook.com/plugins/page.php"', $iframe_out, 'lazy-loading data-src must be preserved');
assert_not_contains('data-data-ccc-src', $iframe_out, 'data-src must not be rewritten as data-data-ccc-src');

$options_property = $reflection->getProperty('options');
$options_property->setValue($frontend, array(
	'block_trackers' => true,
	'block_google'   => false,
	'youtube_nocookie' => false,
));

$output_filter = $reflection->getMethod('filter_output');
$script_html = '<html><body><script src="https://connect.facebook.net/en_US/fbevents.js" data-src="https://example.test/lazy.js"></script></body></html>';
$script_out  = $output_filter->invoke($frontend, $script_html);

assert_contains('data-ccc-src="https://connect.facebook.net/en_US/fbevents.js"', $script_out, 'the real script src must be blocked');
assert_contains('data-src="https://example.test/lazy.js"', $script_out, 'script lazy-loading data-src must be preserved');
assert_not_contains('data-data-ccc-src', $script_out, 'script data-src must not be rewritten as data-data-ccc-src');

echo "PHP security regression tests: OK\n";

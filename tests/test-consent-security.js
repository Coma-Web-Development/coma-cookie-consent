'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const consentSource = fs.readFileSync(
	path.join(__dirname, '..', 'frontend/js/consent.js'),
	'utf8'
);

class FakeElement {
	constructor(attributes) {
		this.attributes = Object.entries(attributes).map(([name, value]) => ({ name, value }));
		this.parentNode = { replaceChild() {} };
		this.hidden = true;
		this.textContent = '';
		this.style = { setProperty() {}, removeProperty() {} };
		this.classList = { toggle() {} };
		this.checked = false;
		this.src = '';
	}

	getAttribute(name) {
		const attribute = this.attributes.find((item) => item.name === name);
		return attribute ? attribute.value : null;
	}

	setAttribute(name, value) {
		const existing = this.attributes.find((item) => item.name === name);
		if (existing) {
			existing.value = String(value);
		} else {
			this.attributes.push({ name, value: String(value) });
		}
		if (name === 'src') this.src = String(value);
	}

	removeAttribute(name) {
		this.attributes = this.attributes.filter((item) => item.name !== name);
	}

	hasAttribute(name) {
		return this.attributes.some((item) => item.name === name);
	}

	addEventListener() {}
	querySelector() { return null; }
	getBoundingClientRect() { return { height: 0, left: 0, right: 0, width: 0 }; }
}

function runConsent({ scriptSrc, iframeSrc }) {
	const script = new FakeElement({
		type: 'text/plain',
		'data-ccc-block': 'marketing',
		'data-ccc-src': scriptSrc,
	});
	const iframe = new FakeElement({
		'data-ccc-block': 'marketing',
		'data-ccc-src': iframeSrc,
	});
	let scriptReplacement = null;
	let iframeReplacement = null;
	script.parentNode = { replaceChild(node, oldNode) { scriptReplacement = { node, oldNode }; } };
	iframe.parentNode = { replaceChild(node, oldNode) { iframeReplacement = { node, oldNode }; } };

	const consent = encodeURIComponent(JSON.stringify({
		v: 1,
		necessary: true,
		functional: true,
		analytics: true,
		marketing: true,
	}));

	const document = {
		readyState: 'complete',
		baseURI: 'https://site.example/',
		cookie: `coma_consent=${consent}`,
		body: { style: {} },
		documentElement: { clientWidth: 1280, style: { setProperty() {} } },
		addEventListener() {},
		createElement(tag) {
			assert.strictEqual(tag, 'script');
			return new FakeElement({});
		},
		getElementById() { return null; },
		querySelectorAll(selector) {
			if (selector === 'script[type="text/plain"][data-ccc-block]') return [script];
			if (selector === 'iframe[data-ccc-block][data-ccc-src]') return [iframe];
			return [];
		},
	};
	const window = {
		cccSettings: {
			lang: 'en',
			blockTrackers: true,
			consentVersion: 1,
			enableAnalytics: true,
			enableMarketing: true,
			revisitButton: false,
			strings: {},
		},
		addEventListener() {},
	};

	const context = {
		window,
		document,
		URL,
		Date,
		Math,
		JSON,
		String,
		Array,
		RegExp,
		encodeURIComponent,
		decodeURIComponent,
		setTimeout,
		clearTimeout,
	};
	vm.runInNewContext(consentSource, context);

	return { script, iframe, scriptReplacement, iframeReplacement };
}

const unsafe = runConsent({
	scriptSrc: 'javascript:alert(1)',
	iframeSrc: 'javascript:alert(1)',
});
assert.strictEqual(unsafe.scriptReplacement, null, 'javascript: script URLs must remain blocked');
assert.strictEqual(unsafe.iframeReplacement, null, 'javascript: iframe URLs must remain blocked');

const safe = runConsent({
	scriptSrc: 'https://tracker.example/pixel.js',
	iframeSrc: 'about:blank',
});
assert.strictEqual(safe.scriptReplacement.node.src, 'https://tracker.example/pixel.js');
assert.strictEqual(safe.iframe.getAttribute('src'), 'about:blank');
assert.strictEqual(safe.iframe.getAttribute('data-ccc-block'), null);

console.log('JavaScript security regression tests: OK');

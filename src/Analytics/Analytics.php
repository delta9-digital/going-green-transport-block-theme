<?php

/**
 * GA4 events for the conversion actions, sent with gtag (loaded by Site Kit).
 *
 * - quote app events: the plugin embed-frame block dispatches `embed-frame:event` with the
 *   prefixed name (e.g. ggt_quote_submitted) and params; sent as-is.
 * - call_click / email_click: tel: and mailto: links, with the page section they sit in.
 * - quote_click: links to the quote page (header button, CTAs).
 * Nothing is sent when gtag is missing (Site Kit off, or a logged-in user Site Kit excludes).
 *
 * @package GoingGreenTransportTheme\Analytics
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Analytics;

use GoingGreenTransportTheme\BlockPatterns\SiteLinks;
use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * Class Analytics
 */
class Analytics implements ServiceInterface
{
	public const HANDLE = 'going-green-analytics';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_enqueue_scripts', [$this, 'enqueue']);
	}

	/**
	 * Inline script in the footer (no dependencies, a few lines).
	 *
	 * @return void
	 */
	public function enqueue(): void
	{
		\wp_register_script(self::HANDLE, false, [], null, ['in_footer' => true]); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		\wp_enqueue_script(self::HANDLE);
		\wp_add_inline_script(self::HANDLE, $this->script());
	}

	/**
	 * Script source.
	 *
	 * @return string
	 */
	private function script(): string
	{
		$quotePath = \wp_json_encode(\untrailingslashit((string) \wp_parse_url(SiteLinks::url('quote'), \PHP_URL_PATH)));

		return <<<JS
(() => {
	const send = (name, params) => typeof window.gtag === 'function' && window.gtag('event', name, params || {});
	// Nearest ancestor class naming a page part: ggt-sec--faq, ggt-hdr, ggt-ftr, ggt-post-cta…
	const sectionOf = (el) => {
		for (let n = el; n && n !== document.body; n = n.parentElement) {
			const c = [...n.classList].find((name) => /^ggt-(sec--|hdr$|ftr__band$|ftr__main$|post-)/.test(name));
			if (c) return c;
		}
		return '';
	};
	const quotePath = {$quotePath};

	document.addEventListener('embed-frame:event', (e) => send(e.detail.event, e.detail.params));

	document.addEventListener('click', (e) => {
		const a = e.target.closest('a[href]');
		if (!a) return;
		const href = a.getAttribute('href');
		const params = { link_text: a.textContent.trim().slice(0, 100), section: sectionOf(a), page_path: location.pathname };
		if (href.startsWith('tel:')) send('call_click', { ...params, phone: href.slice(4) });
		else if (href.startsWith('mailto:')) send('email_click', params);
		else if (new URL(a.href, location.href).pathname.replace(/\/$/, '') === quotePath) send('quote_click', params);
	});
})();
JS;
	}
}

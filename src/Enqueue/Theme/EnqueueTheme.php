<?php

/**
 * The Theme/Frontend Enqueue specific functionality.
 *
 * @package GoingGreenTransportTheme\Enqueue\Theme
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Enqueue\Theme;

use GoingGreenTransportTheme\Config\Config;
use GoingGreenTransportThemeVendor\EightshiftLibs\Cache\ManifestCacheInterface;
use GoingGreenTransportThemeVendor\EightshiftLibs\Enqueue\Theme\AbstractEnqueueTheme;

/**
 * Class EnqueueTheme
 */
class EnqueueTheme extends AbstractEnqueueTheme
{
	/**
	 * Fonts drawn in the first screen of every page (theme.json `fontFace` files).
	 *
	 * Inter is the body text; the Roboto file also serves the "Roboto Condensed" display family
	 * (same variable file at `font-stretch: 75%`), so one request covers headings and the header.
	 */
	private const PRELOAD_FONTS = [
		'assets/fonts/Inter-VariableFont_opsz_wght.latin.woff2',
		'assets/fonts/Roboto-VariableFont_wdth_wght.latin.woff2',
	];

	/**
	 * Roboto Mono: the chip/date row at the top of a blog post; further down the page elsewhere.
	 */
	private const PRELOAD_FONT_POST = 'assets/fonts/RobotoMono-VariableFont_wght.latin.woff2';

	/**
	 * Create a new instance.
	 *
	 * eightshift-libs 8.x injects the manifest *cache*; the previous
	 * `ManifestInterface` type no longer exists, which made the container
	 * silently drop this service (no theme CSS/JS on the front end).
	 *
	 * @param ManifestCacheInterface $manifestCache Inject manifest cache which holds data about assets from manifest.json.
	 */
	public function __construct(ManifestCacheInterface $manifestCache)
	{
		$this->manifestCache = $manifestCache;
	}

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_enqueue_scripts', [$this, 'enqueueStyles'], 10);
		\add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
		\add_action('wp_head', [$this, 'preloadFonts'], 1);
		\add_filter('render_block_core/accordion-item', [$this, 'accordionHiddenState']);
	}

	/**
	 * Closed accordion panels hidden in the server HTML, not only after the script runs.
	 *
	 * WordPress 7.1 binds each panel's `hidden` to `state.isHidden` ("until-found" when closed, so
	 * find-in-page still opens it) but registers no server value for it, so every answer is printed
	 * open and collapses on hydration: the FAQ page's layout shift (CLS 0.15 mobile). This gives the
	 * Interactivity API the same value on the server; on 7.0 the binding is `inert` and it is unused.
	 *
	 * @param string $content Rendered accordion item.
	 *
	 * @return string Unchanged.
	 */
	public function accordionHiddenState(string $content): string
	{
		\wp_interactivity_state('core/accordion', [
			'isHidden' => static function () {
				$context = \wp_interactivity_get_context();

				return empty($context['openByDefault']) ? 'until-found' : null;
			},
		]);

		return $content;
	}

	/**
	 * Preload the above-the-fold font files from the top of <head>.
	 *
	 * Without this the browser only finds them after it has downloaded and applied the stylesheets
	 * and the `@font-face` rules WordPress prints at the end of <head>, so text first paints in the
	 * fallback and then swaps (layout shift). The URL must match the `@font-face` src exactly
	 * (no version query), and `crossorigin` is required for fonts, or the file is fetched twice.
	 *
	 * @return void
	 */
	public function preloadFonts(): void
	{
		$fonts = self::PRELOAD_FONTS;

		if (\is_singular('post')) {
			$fonts[] = self::PRELOAD_FONT_POST;
		}

		foreach ($fonts as $font) {
			\printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				\esc_url(\get_theme_file_uri($font))
			);
		}
	}

	/**
	 * Method that returns assets name used to prefix asset handlers.
	 *
	 * @return string
	 */
	public function getAssetsPrefix(): string
	{
		return Config::getProjectName();
	}

	/**
	 * Method that returns assets version for versioning asset handlers.
	 *
	 * @return string
	 */
	public function getAssetsVersion(): string
	{
		return Config::getProjectVersion();
	}
}

<?php

/**
 * Runtime fixes for theme.json values that cannot be expressed statically.
 *
 * `settings.custom.pattern.*` holds texture URLs written relative to the
 * design package (`url("../assets/…")`). WordPress prints custom values as
 * inline CSS custom properties, where a relative URL resolves against the
 * *page* URL and breaks. This rewrites that prefix to the theme's asset URL.
 *
 * @package GoingGreenTransportTheme\ThemeJson
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\ThemeJson;

use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Theme_JSON_Data;

/**
 * Class ThemeJsonFilters
 */
class ThemeJsonFilters implements ServiceInterface
{
	/**
	 * Prefix used by the design package for texture assets.
	 *
	 * @var string
	 */
	public const DESIGN_ASSET_PREFIX = '../assets/';

	/**
	 * Theme-relative directory the textures are copied to.
	 *
	 * @var string
	 */
	public const THEME_ASSET_DIR = 'assets/images/patterns/';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('wp_theme_json_data_theme', [$this, 'absolutizeCustomAssetUrls']);
	}

	/**
	 * Rewrite design-relative `url("../assets/…")` values under settings.custom
	 * to absolute theme URLs.
	 *
	 * @param WP_Theme_JSON_Data $themeJson Theme JSON data object.
	 *
	 * @return WP_Theme_JSON_Data
	 */
	public function absolutizeCustomAssetUrls(WP_Theme_JSON_Data $themeJson): WP_Theme_JSON_Data
	{
		$data = $themeJson->get_data();
		$custom = $data['settings']['custom'] ?? null;

		if (!\is_array($custom)) {
			return $themeJson;
		}

		$base = \trailingslashit(\get_theme_file_uri(self::THEME_ASSET_DIR));

		\array_walk_recursive(
			$custom,
			static function (&$value) use ($base): void {
				if (\is_string($value) && \str_contains($value, self::DESIGN_ASSET_PREFIX)) {
					$value = \str_replace(self::DESIGN_ASSET_PREFIX, $base, $value);
				}
			}
		);

		return $themeJson->update_with(['version' => $data['version'] ?? 3, 'settings' => ['custom' => $custom]]);
	}
}

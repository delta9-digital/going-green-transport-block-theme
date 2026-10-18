<?php

/**
 * How It Works route map: server-renders the map shell inside the `ggt-route-scene` group (the
 * plugin scroll-story panel), so no asset URLs are stored in post content and the base map
 * shows without JavaScript. assets/scripts/route-scene/ builds and animates the rest.
 *
 * @package GoingGreenTransportTheme\RouteScene
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\RouteScene;

use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * Class RouteScene
 */
class RouteScene implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('render_block_core/group', [$this, 'render'], 10, 2);
	}

	/**
	 * Replace the group's placeholder content with the map shell.
	 *
	 * @param string $content Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 *
	 * @return string
	 */
	public function render(string $content, array $block): string
	{
		$className = (string) ($block['attrs']['className'] ?? '');

		if (!\preg_match('/(?:^|\s)ggt-route-scene(?:\s|$)/', $className)) {
			return $content;
		}

		$variant = \str_contains($className, 'ggt-route-scene--on-demand') ? 'on-demand' : 'regional';
		$base = \trailingslashit(\get_theme_file_uri('assets/images/map'));

		[$map, $alt] = $variant === 'on-demand'
			? ['mn-counties.svg', \__('Map of Minnesota: a direct run from Duluth to a Minneapolis testing lab.', 'going-green-transport')]
			: ['mn-regions-outlined.svg', \__('Map of Minnesota\'s five route regions: an example Northeast route from Duluth to a Minneapolis testing lab.', 'going-green-transport')];

		return \sprintf(
			'<div class="wp-block-group %1$s" data-variant="%2$s" data-map-base="%3$s"><div class="ggt-rs__stage"><div class="ggt-rs__box"><div class="ggt-rs__cam"><img class="ggt-rs__layer ggt-rs__base" src="%4$s" alt="%5$s" /></div></div></div></div>',
			\esc_attr($className),
			\esc_attr($variant),
			\esc_url($base),
			\esc_url($base . $map),
			\esc_attr($alt)
		);
	}
}

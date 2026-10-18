<?php

/**
 * Region taxonomy for locations: the five regional routes (Central Minnesota, Northeast,
 * Southeast, Northwest, Southwest). Each term carries its route day in `ggt_route_day`.
 *
 * @package GoingGreenTransportTheme\CustomTaxonomy
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\CustomTaxonomy;

use GoingGreenTransportTheme\CustomMeta\AbstractPostMeta;
use GoingGreenTransportTheme\CustomPostType\LocationPostType;
use GoingGreenTransportThemeVendor\EightshiftLibs\CustomTaxonomy\AbstractTaxonomy;

/**
 * Class RegionTaxonomy
 */
class RegionTaxonomy extends AbstractTaxonomy
{
	public const TAXONOMY_SLUG = 'region';
	public const REST_API_ENDPOINT_SLUG = 'regions';
	public const ROUTE_DAY_META = AbstractPostMeta::PREFIX . 'route_day';

	/**
	 * Register the taxonomy and its term meta.
	 *
	 * @return void
	 */
	public function register(): void
	{
		parent::register();
		\add_action('init', [$this, 'registerTermMeta']);
	}

	/**
	 * Route day per region (e.g. "Wed"), shown on location cards.
	 *
	 * @return void
	 */
	public function registerTermMeta(): void
	{
		\register_term_meta(
			self::TAXONOMY_SLUG,
			self::ROUTE_DAY_META,
			[
				'type' => 'string',
				'single' => true,
				'default' => '',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest' => true,
			]
		);
	}

	/**
	 * Get the slug of the custom taxonomy.
	 *
	 * @return string
	 */
	protected function getTaxonomySlug(): string
	{
		return static::TAXONOMY_SLUG;
	}

	/**
	 * Get the post type slug(s) that use the taxonomy.
	 *
	 * @return string
	 */
	protected function getPostTypeSlug()
	{
		return LocationPostType::POST_TYPE_SLUG;
	}

	/**
	 * Get the arguments that configure the custom taxonomy.
	 *
	 * @return array<mixed>
	 */
	protected function getTaxonomyArguments(): array
	{
		return [
			// phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
			'labels' => [
				'name' => esc_html_x('Regions', 'taxonomy plural name', 'going-green-transport'),
				'singular_name' => esc_html_x('Region', 'taxonomy singular name', 'going-green-transport'),
			],
			// phpcs:enable
			'hierarchical' => true,
			'show_ui' => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'public' => false,
			'show_in_rest' => true,
			'query_var' => false,
			'rest_base' => static::REST_API_ENDPOINT_SLUG,
			'rewrite' => false,
		];
	}
}

<?php

/**
 * Region taxonomy for locations: the five regional routes (Central Minnesota, Northeast,
 * Southeast, Northwest, Southwest). Each term carries its zone label in `ggt_zone`.
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
	public const ZONE_META = AbstractPostMeta::PREFIX . 'zone';

	/**
	 * Register the taxonomy and its term meta.
	 *
	 * @return void
	 */
	public function register(): void
	{
		parent::register();
		\add_action('init', [$this, 'registerTermMeta']);
		\add_filter('post_class', [$this, 'postClass'], 10, 3);
	}

	/**
	 * Add region-{slug} to a location's classes. WordPress adds term classes only for viewable
	 * taxonomies and this one is private (no archives); the Locations hub route chips (plugin
	 * filter-chips) match on these classes.
	 *
	 * @param array<int, string> $classes Post classes.
	 * @param array<int, string> $extra Extra classes passed to get_post_class().
	 * @param int $postId Post ID.
	 *
	 * @return array<int, string>
	 */
	public function postClass(array $classes, array $extra, int $postId): array
	{
		$terms = \get_the_terms($postId, self::TAXONOMY_SLUG);

		if (!\is_array($terms)) {
			return $classes;
		}

		foreach ($terms as $term) {
			$classes[] = \sanitize_html_class(self::TAXONOMY_SLUG . '-' . $term->slug, self::TAXONOMY_SLUG . '-' . $term->term_id);
		}

		return $classes;
	}

	/**
	 * Zone per region (e.g. "Zone 3"), shown on location cards.
	 *
	 * @return void
	 */
	public function registerTermMeta(): void
	{
		\register_term_meta(
			self::TAXONOMY_SLUG,
			self::ZONE_META,
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

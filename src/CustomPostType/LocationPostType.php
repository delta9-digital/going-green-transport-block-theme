<?php

/**
 * Location post type: one post per Minnesota city served.
 *
 * Permalinks keep the live SEO URLs, /cannabis-transport-in-{city}-mn/ (see
 * Location\LocationRouting), so the post type has no rewrite base of its own.
 *
 * @package GoingGreenTransportTheme\CustomPostType
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\CustomPostType;

use GoingGreenTransportThemeVendor\EightshiftLibs\CustomPostType\AbstractPostType;

/**
 * Class LocationPostType
 */
class LocationPostType extends AbstractPostType
{
	public const POST_TYPE_SLUG = 'location';
	public const REST_API_ENDPOINT_SLUG = 'locations';
	public const MENU_POSITION = 21;
	public const MENU_ICON = 'dashicons-location';

	/**
	 * Get the slug to use for the custom post type.
	 *
	 * @return string
	 */
	protected function getPostTypeSlug(): string
	{
		return self::POST_TYPE_SLUG;
	}

	/**
	 * Get the arguments that configure the custom post type.
	 *
	 * @return array<mixed>
	 */
	protected function getPostTypeArguments(): array
	{
		return [
			// phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
			'labels' => [
				'name' => esc_html_x('Locations', 'post type plural name', 'going-green-transport'),
				'singular_name' => esc_html_x('Location', 'post type singular name', 'going-green-transport'),
				'add_new_item' => esc_html__('Add new location', 'going-green-transport'),
				'edit_item' => esc_html__('Edit location', 'going-green-transport'),
				'all_items' => esc_html__('All locations', 'going-green-transport'),
			],
			// phpcs:enable
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'query_var' => true,
			'capability_type' => 'post',
			'has_archive' => false,
			'rewrite' => false,
			'hierarchical' => false,
			'menu_icon' => static::MENU_ICON,
			'menu_position' => static::MENU_POSITION,
			'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields'],
			'show_in_rest' => true,
			'rest_base' => static::REST_API_ENDPOINT_SLUG,
		];
	}
}

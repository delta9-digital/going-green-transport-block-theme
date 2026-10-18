<?php

/**
 * Testimonial post type: client reviews (seeded from Google reviews), shown in carousels.
 * Not publicly queryable: testimonials have no pages of their own.
 *
 * @package GoingGreenTransportTheme\CustomPostType
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\CustomPostType;

use GoingGreenTransportThemeVendor\EightshiftLibs\CustomPostType\AbstractPostType;

/**
 * Class TestimonialPostType
 */
class TestimonialPostType extends AbstractPostType
{
	public const POST_TYPE_SLUG = 'testimonial';
	public const REST_API_ENDPOINT_SLUG = 'testimonials';
	public const MENU_POSITION = 22;
	public const MENU_ICON = 'dashicons-format-quote';

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
				'name' => esc_html_x('Testimonials', 'post type plural name', 'going-green-transport'),
				'singular_name' => esc_html_x('Testimonial', 'post type singular name', 'going-green-transport'),
				'add_new_item' => esc_html__('Add new testimonial', 'going-green-transport'),
				'edit_item' => esc_html__('Edit testimonial', 'going-green-transport'),
				'all_items' => esc_html__('All testimonials', 'going-green-transport'),
			],
			// phpcs:enable
			'public' => false,
			'publicly_queryable' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'query_var' => false,
			'capability_type' => 'post',
			'has_archive' => false,
			'rewrite' => false,
			'hierarchical' => false,
			'menu_icon' => static::MENU_ICON,
			'menu_position' => static::MENU_POSITION,
			'supports' => ['title', 'editor', 'thumbnail', 'revisions', 'custom-fields'],
			'show_in_rest' => true,
			'rest_base' => static::REST_API_ENDPOINT_SLUG,
		];
	}
}

<?php

/**
 * Block bindings source `ggt/location`: location data for core blocks in the city template.
 *
 * Usage: {"metadata":{"bindings":{"content":{"source":"ggt/location","args":{"key":"city"}}}}}
 * Keys: city, region, route_day, route (e.g. "Southeast Route · Wed"), nearby (comma list).
 *
 * @package GoingGreenTransportTheme\Location
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Location;

use GoingGreenTransportTheme\CustomMeta\AbstractPostMeta;
use GoingGreenTransportTheme\CustomPostType\LocationPostType;
use GoingGreenTransportTheme\CustomTaxonomy\RegionTaxonomy;
use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Block;
use WP_Term;

/**
 * Class LocationBindings
 */
class LocationBindings implements ServiceInterface
{
	public const SOURCE = 'ggt/location';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerSource']);
	}

	/**
	 * Register the bindings source.
	 *
	 * @return void
	 */
	public function registerSource(): void
	{
		\register_block_bindings_source(
			self::SOURCE,
			[
				'label' => \__('Location', 'going-green-transport'),
				'get_value_callback' => [$this, 'getValue'],
				'uses_context' => ['postId', 'postType'],
			]
		);
	}

	/**
	 * Value for a binding.
	 *
	 * @param array<string, mixed> $args Binding args (`key`).
	 * @param WP_Block $block Block instance.
	 *
	 * @return string|null
	 */
	public function getValue(array $args, WP_Block $block): ?string
	{
		$postId = (int) ($block->context['postId'] ?? \get_the_ID());

		if (!$postId || \get_post_type($postId) !== LocationPostType::POST_TYPE_SLUG) {
			return null;
		}

		$meta = static fn(string $key) => \get_post_meta($postId, AbstractPostMeta::PREFIX . $key, true);
		$region = $this->region($postId);
		$day = $region ? (string) \get_term_meta($region->term_id, RegionTaxonomy::ROUTE_DAY_META, true) : '';

		$value = match ((string) ($args['key'] ?? '')) {
			'city' => (string) ($meta('city') ?: \get_the_title($postId)),
			'region' => $region->name ?? '',
			'route_day' => $day,
			'route' => $region ? \trim(\sprintf(
				/* translators: 1: region name, 2: route day. */
				\__('%1$s Route · %2$s', 'going-green-transport'),
				$region->name,
				$day
			), ' ·') : '',
			'nearby' => \implode(', ', (array) $meta('nearby')),
			default => null,
		};

		return $value === null ? null : \esc_html($value);
	}

	/**
	 * First region term of a location.
	 *
	 * @param int $postId Location ID.
	 *
	 * @return WP_Term|null
	 */
	private function region(int $postId): ?WP_Term
	{
		$terms = \get_the_terms($postId, RegionTaxonomy::TAXONOMY_SLUG);

		return \is_array($terms) && $terms ? $terms[0] : null;
	}
}

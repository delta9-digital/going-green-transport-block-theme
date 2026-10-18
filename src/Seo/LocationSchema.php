<?php

/**
 * Schema for city pages: a Service piece (licensed cannabis transport) with the city as
 * areaServed (plus nearby towns), provided by Yoast's Organization. Added to Yoast's graph.
 *
 * @package GoingGreenTransportTheme\Seo
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Seo;

use GoingGreenTransportTheme\CustomMeta\AbstractPostMeta;
use GoingGreenTransportTheme\CustomPostType\LocationPostType;
use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * Class LocationSchema
 */
class LocationSchema implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('wpseo_schema_graph', [$this, 'addService'], 10, 2);
	}

	/**
	 * Append the Service piece on a location.
	 *
	 * @param array<int, array<string, mixed>> $graph Schema graph.
	 * @param object $context Yoast Meta_Tags_Context (site_url, canonical).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addService(array $graph, $context): array
	{
		if (!\is_singular(LocationPostType::POST_TYPE_SLUG)) {
			return $graph;
		}

		$postId = (int) \get_queried_object_id();
		$meta = static fn(string $key) => \get_post_meta($postId, AbstractPostMeta::PREFIX . $key, true);
		$city = (string) ($meta('city') ?: \get_the_title($postId));
		$canonical = (string) ($context->canonical ?? \get_permalink($postId));

		$area = ['@type' => 'City', 'name' => "{$city}, MN"];

		if ($meta('lat') !== '' && $meta('lng') !== '') {
			$area['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $meta('lat'), 'longitude' => (float) $meta('lng')];
		}

		$nearby = \array_map(
			static fn(string $place) => ['@type' => 'Place', 'name' => \str_contains($place, ',') ? $place : "{$place}, MN"],
			\array_filter(\array_map('trim', (array) $meta('nearby')))
		);

		$graph[] = [
			'@type' => 'Service',
			'@id' => "{$canonical}#service",
			'name' => \sprintf(\__('Licensed cannabis transport in %s, MN', 'going-green-transport'), $city),
			'serviceType' => \__('Licensed cannabis transport', 'going-green-transport'),
			'provider' => ['@id' => \trailingslashit((string) ($context->site_url ?? \home_url('/'))) . '#organization'],
			'areaServed' => \array_values(\array_merge([$area], $nearby)),
			'url' => $canonical,
		];

		return $graph;
	}
}

<?php

/**
 * Serves location posts at the live SEO URLs, /cannabis-transport-in-{city}-mn/.
 *
 * The post type has no rewrite base. Permalinks are built here, and a request for that URL
 * is routed to the location only when a published location with the city slug exists, so
 * the existing city pages keep working until they are migrated (BUILD-PLAN, locations round).
 *
 * @package GoingGreenTransportTheme\Location
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Location;

use GoingGreenTransportTheme\CustomPostType\LocationPostType;
use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * Class LocationRouting
 */
class LocationRouting implements ServiceInterface
{
	/**
	 * URL pattern around the location slug.
	 *
	 * @var string
	 */
	public const PATTERN = '#^cannabis-transport-in-([a-z0-9-]+)-mn/?$#';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('post_type_link', [$this, 'permalink'], 10, 2);
		\add_filter('request', [$this, 'route']);
		\add_action('template_redirect', [$this, 'redirectToCanonical']);
	}

	/**
	 * Permalink for a location.
	 *
	 * @param string $link Default permalink.
	 * @param WP_Post $post Post.
	 *
	 * @return string
	 */
	public function permalink(string $link, WP_Post $post): string
	{
		if ($post->post_type !== LocationPostType::POST_TYPE_SLUG || $post->post_name === '') {
			return $link;
		}

		return \home_url("/cannabis-transport-in-{$post->post_name}-mn/");
	}

	/**
	 * Route a city URL to its location post when one is published.
	 *
	 * WordPress parses these URLs as pages (`pagename`); swap the query only when a location
	 * with that slug exists, otherwise leave the page request alone.
	 *
	 * @param array<string, mixed> $vars Query vars.
	 *
	 * @return array<string, mixed>
	 */
	public function route(array $vars): array
	{
		$path = (string) ($vars['pagename'] ?? $vars['name'] ?? '');

		if ($path === '' || !\preg_match(self::PATTERN, $path, $matches)) {
			return $vars;
		}

		$location = \get_page_by_path($matches[1], \OBJECT, LocationPostType::POST_TYPE_SLUG);

		if (!$location instanceof WP_Post || $location->post_status !== 'publish') {
			return $vars;
		}

		return [
			LocationPostType::POST_TYPE_SLUG => $location->post_name,
			'post_type' => LocationPostType::POST_TYPE_SLUG,
			'name' => $location->post_name,
		];
	}

	/**
	 * 301 any other URL for a location (e.g. ?location=slug) to its permalink, so each city has
	 * one indexable URL.
	 *
	 * @return void
	 */
	public function redirectToCanonical(): void
	{
		if (!\is_singular(LocationPostType::POST_TYPE_SLUG)) {
			return;
		}

		$canonical = (string) \get_permalink();
		$requested = \home_url(\wp_parse_url((string) \wp_unslash($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH) ?: '/'); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$hasQuery = isset($_GET[LocationPostType::POST_TYPE_SLUG]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ($canonical && ($hasQuery || \untrailingslashit($requested) !== \untrailingslashit($canonical))) {
			\wp_safe_redirect($canonical, 301);
			exit;
		}
	}
}

<?php

/**
 * Single post and Guide templates (comp `GGT Blog Post`).
 *
 * - Featured image fallback: a post without one shows its category's icon on a plate, so the
 *   hero keeps its two-column layout until real photos land.
 * - "More Posts" (Query Loop queryId 3) leaves out the post being read.
 *
 * @package GoingGreenTransportTheme\Blog
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Blog;

use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Block;

/**
 * Class PostTemplates
 */
class PostTemplates implements ServiceInterface
{
	public const MORE_POSTS_QUERY_ID = 3;

	/**
	 * Category slug => icon file in assets/images/icons.
	 */
	private const ICONS = [
		'cultivators' => 'cultivators.svg',
		'manufacturers' => 'manufacturer.svg',
		'testing' => 'labs.svg',
	];

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('render_block_core/post-featured-image', [$this, 'featuredImageFallback'], 10, 3);
		\add_filter('query_loop_block_query_vars', [$this, 'excludeCurrent'], 10, 2);
	}

	/**
	 * Icon plate when the hero image block has no image.
	 *
	 * @param string $content Rendered block.
	 * @param array<string, mixed> $parsed Parsed block.
	 * @param WP_Block $block Block instance.
	 *
	 * @return string
	 */
	public function featuredImageFallback(string $content, array $parsed, WP_Block $block): string
	{
		$className = (string) ($parsed['attrs']['className'] ?? '');

		if ($content !== '' || !\str_contains($className, 'ggt-post-hero__image')) {
			return $content;
		}

		$postId = (int) ($block->context['postId'] ?? \get_the_ID());
		$icon = 'cultivators.svg';

		if (\get_post_type($postId) === 'page') {
			$icon = match (true) {
				\str_contains((string) \get_post_field('post_name', $postId), 'testing') => 'labs.svg',
				\str_contains((string) \get_post_field('post_name', $postId), 'manufacturer') => 'manufacturer.svg',
				default => 'cultivators.svg',
			};
		}

		foreach (\get_the_category($postId) as $term) {
			if (isset(self::ICONS[$term->slug])) {
				$icon = self::ICONS[$term->slug];
				break;
			}
		}

		return \sprintf(
			'<figure class="wp-block-post-featured-image %1$s ggt-post-hero__image--fallback"><img src="%2$s" alt="" /></figure>',
			\esc_attr($className),
			\esc_url(\get_theme_file_uri("assets/images/icons/{$icon}"))
		);
	}

	/**
	 * Leave the current post out of "More Posts".
	 *
	 * @param array<string, mixed> $query Query vars.
	 * @param WP_Block $block Post Template block.
	 *
	 * @return array<string, mixed>
	 */
	public function excludeCurrent(array $query, WP_Block $block): array
	{
		if ((int) ($block->context['queryId'] ?? 0) !== self::MORE_POSTS_QUERY_ID || !\is_singular()) {
			return $query;
		}

		$query['post__not_in'] = \array_merge((array) ($query['post__not_in'] ?? []), [\get_queried_object_id()]);

		return $query;
	}
}

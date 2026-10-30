<?php

/**
 * Location hero slideshow images: load only what the hero frame needs.
 *
 * The hero is a carousel set to "Fill the container": every slide covers a portrait frame
 * (about 560x770 on desktop, full width x 320 on phones). WordPress would describe each image as
 * 100vw wide and load every slide up front, so the first slide (the LCP image) shared the
 * bandwidth with slide 2 and came down at 1536px. Here:
 *
 * - `sizes` is the width the cover crop really draws: on desktop the frame height times the
 *   photo's aspect ratio (a landscape photo is cropped from the sides), on phones the frame width.
 * - Slides after the first are lazy. Until the carousel script starts only slide 1 is displayed,
 *   so slide 2 loads after the page has painted. WordPress prefixes lazy images' `sizes` with
 *   `auto` (the element's box width), which is narrower than a cover crop draws, so that is
 *   taken off again.
 * - Slide 1 is preloaded from the top of <head>, so its request starts before the stylesheets.
 *
 * @package GoingGreenTransportTheme\Location
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Location;

use GoingGreenTransportTheme\CustomPostType\LocationPostType;
use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;
use WP_HTML_Tag_Processor;

/**
 * Class LocationHeroImages
 */
class LocationHeroImages implements ServiceInterface
{
	/**
	 * Hero frame on desktop (CSS px), tallest of the 24 location pages.
	 */
	private const FRAME_WIDTH = 560;
	private const FRAME_HEIGHT = 770;

	/**
	 * Start of every `sizes` value set here (also how they are recognised later): below 782px the
	 * columns stack and the frame is the content width.
	 */
	private const SIZES_PREFIX = '(max-width: 781px) calc(100vw - 48px), ';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('render_block_eightshift-boilerplate/carousel', [$this, 'optimizeSlides'], 10, 2);
		\add_filter('wp_content_img_tag', [$this, 'dropAutoSizes']);
		\add_action('wp_head', [$this, 'preloadFirstSlide'], 1);
	}

	/**
	 * Set `sizes` on every slide image and lazy-load all but the first.
	 *
	 * @param string $content Rendered block.
	 * @param array<string, mixed> $parsed Parsed block.
	 *
	 * @return string
	 */
	public function optimizeSlides(string $content, array $parsed): string
	{
		if (empty($parsed['attrs']['carouselFill'])) {
			return $content;
		}

		$tags = new WP_HTML_Tag_Processor($content);
		$index = 0;

		while ($tags->next_tag('img')) {
			$sizes = $this->sizes($this->attachmentId((string) $tags->get_attribute('class')));

			if ($sizes !== '') {
				$tags->set_attribute('sizes', $sizes);
			}

			if ($index > 0) {
				$tags->set_attribute('loading', 'lazy');
			}

			$index++;
		}

		return $tags->get_updated_html();
	}

	/**
	 * Preload the first slide of a location's hero carousel.
	 *
	 * @return void
	 */
	public function preloadFirstSlide(): void
	{
		if (!\is_singular(LocationPostType::POST_TYPE_SLUG)) {
			return;
		}

		$id = $this->firstSlideId(\parse_blocks((string) \get_post_field('post_content', \get_queried_object_id())));
		$srcset = $id ? \wp_get_attachment_image_srcset($id, 'full') : false;

		if (!$srcset) {
			return;
		}

		\printf(
			'<link rel="preload" as="image" href="%s" imagesrcset="%s" imagesizes="%s" fetchpriority="high">' . "\n",
			\esc_url((string) \wp_get_attachment_image_url($id, 'full')),
			\esc_attr($srcset),
			\esc_attr($this->sizes($id))
		);
	}

	/**
	 * First image of the first fill carousel in a block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param bool $inCarousel Whether the blocks sit inside a fill carousel.
	 *
	 * @return int
	 */
	private function firstSlideId(array $blocks, bool $inCarousel = false): int
	{
		foreach ($blocks as $block) {
			if ($inCarousel && $block['blockName'] === 'core/image' && !empty($block['attrs']['id'])) {
				return (int) $block['attrs']['id'];
			}

			$isFill = $block['blockName'] === 'eightshift-boilerplate/carousel' && !empty($block['attrs']['carouselFill']);
			$id = $this->firstSlideId($block['innerBlocks'] ?? [], $inCarousel || $isFill);

			if ($id) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * `sizes` for a slide image: the width its cover crop draws.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return string Empty when the image size is unknown.
	 */
	private function sizes(int $id): string
	{
		$meta = $id ? \wp_get_attachment_metadata($id) : false;

		if (!\is_array($meta) || empty($meta['width']) || empty($meta['height'])) {
			return '';
		}

		$drawn = (int) \ceil(\max(self::FRAME_WIDTH, self::FRAME_HEIGHT * $meta['width'] / $meta['height']));

		return self::SIZES_PREFIX . \min($drawn, (int) $meta['width']) . 'px';
	}

	/**
	 * Take `auto` back off the slide images' `sizes`.
	 *
	 * @param string $image Image tag.
	 *
	 * @return string
	 */
	public function dropAutoSizes(string $image): string
	{
		return \str_replace('sizes="auto, ' . self::SIZES_PREFIX, 'sizes="' . self::SIZES_PREFIX, $image);
	}

	/**
	 * Attachment ID from the core/image `wp-image-<id>` class.
	 *
	 * @param string $class Image class attribute.
	 *
	 * @return int
	 */
	private function attachmentId(string $class): int
	{
		return \preg_match('/\bwp-image-(\d+)\b/', $class, $matches) ? (int) $matches[1] : 0;
	}
}

<?php

/**
 * Replace each location's placeholder hero image with a 2-slide carousel (landmark, downtown).
 *
 * Usage (WP-CLI), after bin/location-hero-images.php:
 *   wp eval-file bin/location-hero-slideshow.php [city=<slug>] [dry-run]
 *
 * In the hero section (`.ggt-sec--hero`) the core/image whose attachment is
 * `city-hero-<city>.svg` becomes an `eightshift-boilerplate/carousel` set to fill its frame
 * (fade, autoplay 6 s, dots, loop), each slide a core/image of the imported photo. Posts whose
 * hero already holds the carousel are skipped. Saves with wp_update_post (keeps a revision).
 * The old SVG attachments stay in the Media Library.
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtOpts = [];
foreach ($args ?? [] as $ggtArg) {
	[$ggtKey, $ggtVal] = array_pad(explode('=', $ggtArg, 2), 2, true);
	$ggtOpts[$ggtKey] = $ggtVal;
}

$ggtDryRun = isset($ggtOpts['dry-run']);

/**
 * Attachment ID of an imported hero photo (`_ggt_hero_key` = `<city>-<slide>`).
 */
$ggtPhotoId = static function (string $key): int {
	$ids = get_posts([
		'post_type' => 'attachment',
		'post_status' => 'inherit',
		'meta_key' => '_ggt_hero_key',
		'meta_value' => $key,
		'fields' => 'ids',
		'numberposts' => 1,
	]);

	return $ids ? (int) $ids[0] : 0;
};

/**
 * A parsed core/image block for an attachment, as the editor saves it.
 */
$ggtImageBlock = static function (int $id): array {
	$src = wp_get_attachment_image_url($id, 'full');
	$alt = get_post_meta($id, '_wp_attachment_image_alt', true);
	$html = "\n" . '<figure class="wp-block-image size-full"><img src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '" class="wp-image-' . $id . '"/></figure>' . "\n";

	return [
		'blockName' => 'core/image',
		'attrs' => ['id' => $id, 'sizeSlug' => 'full', 'linkDestination' => 'none'],
		'innerBlocks' => [],
		'innerHTML' => $html,
		'innerContent' => [$html],
	];
};

/**
 * Wrap inner blocks the way InnerBlocks.Content saves them (newline-separated).
 */
$ggtParent = static function (string $name, array $attrs, array $inner): array {
	$content = ["\n"];
	foreach ($inner as $i => $block) {
		$content[] = null;
		$content[] = "\n";
	}

	return [
		'blockName' => $name,
		'attrs' => $attrs,
		'innerBlocks' => $inner,
		'innerHTML' => str_repeat("\n", count($inner) + 1),
		'innerContent' => $content,
	];
};

$ggtCarousel = static function (array $ids) use ($ggtImageBlock, $ggtParent): array {
	$slides = array_map(
		static fn (int $id): array => $ggtParent(
			'eightshift-boilerplate/carousel-item',
			['wrapperUse' => false, 'carouselItemUseContent' => true],
			[$ggtImageBlock($id)]
		),
		$ids
	);

	return $ggtParent('eightshift-boilerplate/carousel', [
		'wrapperUse' => false,
		'carouselIsLoop' => true,
		'carouselShowItems' => 1,
		'carouselShowPagination' => true,
		'carouselIsAutoplay' => true,
		'carouselAutoplayDelay' => 6000,
		'carouselEffect' => 'fade',
		'carouselFill' => true,
	], $slides);
};

/**
 * Walk a hero section and swap the placeholder image. Returns 'converted', 'done' or 'missing'.
 */
$ggtSwap = static function (array &$blocks, string $city, array $carousel) use (&$ggtSwap): string {
	foreach ($blocks as &$block) {
		if ($block['blockName'] === 'eightshift-boilerplate/carousel') {
			return 'done';
		}

		if ($block['blockName'] === 'core/image' && !empty($block['attrs']['id'])) {
			$file = basename((string) get_attached_file((int) $block['attrs']['id']));

			if ($file === "city-hero-{$city}.svg") {
				$block = $carousel;
				return 'converted';
			}
		}

		if (!empty($block['innerBlocks'])) {
			$result = $ggtSwap($block['innerBlocks'], $city, $carousel);

			if ($result !== 'missing') {
				return $result;
			}
		}
	}

	return 'missing';
};

$ggtLocations = get_posts([
	'post_type' => 'location',
	'post_status' => 'publish',
	'numberposts' => -1,
	'orderby' => 'name',
	'order' => 'ASC',
]);

foreach ($ggtLocations as $ggtPost) {
	$ggtCity = $ggtPost->post_name;

	if (isset($ggtOpts['city']) && $ggtOpts['city'] !== $ggtCity) {
		continue;
	}

	$ggtIds = [$ggtPhotoId("{$ggtCity}-landmark"), $ggtPhotoId("{$ggtCity}-downtown")];

	if (in_array(0, $ggtIds, true)) {
		WP_CLI::warning("{$ggtCity}: photos not imported, run bin/location-hero-images.php first");
		continue;
	}

	$ggtBlocks = parse_blocks($ggtPost->post_content);
	$ggtResult = 'missing';

	foreach ($ggtBlocks as &$ggtSection) {
		if (strpos($ggtSection['attrs']['className'] ?? '', 'ggt-sec--hero') === false) {
			continue;
		}

		$ggtSectionBlocks = [$ggtSection];
		$ggtResult = $ggtSwap($ggtSectionBlocks, $ggtCity, $ggtCarousel($ggtIds));
		$ggtSection = $ggtSectionBlocks[0];
		break;
	}
	unset($ggtSection);

	if ($ggtResult !== 'converted') {
		WP_CLI::log("{$ggtCity}\t" . ($ggtResult === 'done' ? 'already converted, skipped' : 'no placeholder hero image found, skipped'));
		continue;
	}

	$ggtNew = serialize_blocks($ggtBlocks);

	if ($ggtDryRun) {
		$ggtBefore = wp_tempnam('before');
		$ggtAfter = wp_tempnam('after');
		file_put_contents($ggtBefore, $ggtPost->post_content);
		file_put_contents($ggtAfter, $ggtNew);
		WP_CLI::log("{$ggtCity}\t(dry run)");
		WP_CLI::log((string) shell_exec('diff -u ' . escapeshellarg($ggtBefore) . ' ' . escapeshellarg($ggtAfter) . " --label {$ggtCity}-before --label {$ggtCity}-after"));
		unlink($ggtBefore);
		unlink($ggtAfter);
		continue;
	}

	$ggtSaved = wp_update_post(['ID' => $ggtPost->ID, 'post_content' => wp_slash($ggtNew)], true);

	if (is_wp_error($ggtSaved)) {
		WP_CLI::warning("{$ggtCity}: " . $ggtSaved->get_error_message());
		continue;
	}

	WP_CLI::log("{$ggtCity}\tconverted (post {$ggtPost->ID}, slides {$ggtIds[0]}, {$ggtIds[1]})");
}

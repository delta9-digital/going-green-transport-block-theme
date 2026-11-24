<?php

/**
 * About Us: a photo in the hero's right column instead of the Minnesota mark.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/about-hero-photo.php [dry-run]
 *
 * The hero's right column held a dark green "Minnesota mark" group (`.ggt-hero-mark`). It becomes the same rounded
 * photo group the Services and Flat-Rate heroes use (`.ggt-clip` + a cover `is-style-ggt-rounded ggt-photo` image)
 * with ggt-rural-route-aerial.jpg (a cargo van on a rural Minnesota road at sunrise). The founder cards keep their
 * Minnesota mark until there are headshots.
 *
 * Attachment found by file name. Idempotent; saves with wp_update_post (keeps a revision to roll back to).
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);

$ggtPage = get_page_by_path('about-us');

if (!$ggtPage) {
	WP_CLI::error('No page with the slug about-us.');
}

$ggtIds = get_posts([
	'post_type' => 'attachment',
	'post_status' => 'inherit',
	'fields' => 'ids',
	'numberposts' => 1,
	'meta_query' => [['key' => '_wp_attached_file', 'value' => 'ggt-rural-route-aerial', 'compare' => 'LIKE']],
]);

if (!$ggtIds) {
	WP_CLI::error('Attachment ggt-rural-route-aerial not found.');
}

$ggtPhoto = (int) $ggtIds[0];
$ggtUrl = (string) wp_get_attachment_url($ggtPhoto);
$ggtAlt = (string) get_post_meta($ggtPhoto, '_wp_attachment_image_alt', true);

$ggtContent = $ggtPage->post_content;
$ggtBlocks = parse_blocks($ggtContent);

// First .ggt-hero-mark group inside the .ggt-sec--hero section.
$ggtMark = null;
$ggtFindHero = static function (array $blocks) use (&$ggtFindHero) {
	foreach ($blocks as $block) {
		if (($block['blockName'] ?? '') === 'core/group' && strpos((string) ($block['attrs']['className'] ?? ''), 'ggt-sec--hero') !== false) {
			return $block;
		}
		$found = $ggtFindHero($block['innerBlocks'] ?? []);
		if ($found) {
			return $found;
		}
	}
	return null;
};
$ggtFindMark = static function (array $blocks) use (&$ggtFindMark) {
	foreach ($blocks as $block) {
		if (($block['blockName'] ?? '') === 'core/group' && in_array('ggt-hero-mark', explode(' ', (string) ($block['attrs']['className'] ?? '')), true)) {
			return $block;
		}
		$found = $ggtFindMark($block['innerBlocks'] ?? []);
		if ($found) {
			return $found;
		}
	}
	return null;
};

$ggtHero = $ggtFindHero($ggtBlocks);
$ggtMark = $ggtHero ? $ggtFindMark($ggtHero['innerBlocks'] ?? []) : null;

if (!$ggtMark) {
	WP_CLI::log(strpos(serialize_blocks($ggtHero ? [$ggtHero] : []), 'ggt-rural-route-aerial') !== false
		? 'About Us: hero already has the photo; skipped.'
		: 'About Us: no Minnesota mark in the hero; skipped.');
	return;
}

$ggtOld = serialize_block($ggtMark);

if (substr_count($ggtContent, $ggtOld) !== 1) {
	WP_CLI::error('About Us: could not locate the hero mark markup exactly once; nothing changed.');
}

$ggtNew = '<!-- wp:group {"className":"ggt-clip","style":{"spacing":{"blockGap":"0"},"border":{"radius":"24px"},"dimensions":{"minHeight":"320px"}},"layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group ggt-clip" style="border-radius:24px;min-height:320px"><!-- wp:image {"id":' . $ggtPhoto . ',"scale":"cover","sizeSlug":"full","className":"is-style-ggt-rounded ggt-photo"} -->' . "\n"
	. '<figure class="wp-block-image size-full is-style-ggt-rounded ggt-photo"><img src="' . esc_url($ggtUrl) . '" alt="' . esc_attr($ggtAlt) . '" class="wp-image-' . $ggtPhoto . '" style="object-fit:cover"/></figure>' . "\n"
	. '<!-- /wp:image --></div>' . "\n"
	. '<!-- /wp:group -->';

if ($ggtDryRun) {
	WP_CLI::log("[dry-run] About Us ({$ggtPage->ID}): would replace the hero Minnesota mark with attachment {$ggtPhoto} ({$ggtUrl}).");
	return;
}

$ggtResult = wp_update_post(wp_slash(['ID' => $ggtPage->ID, 'post_content' => str_replace($ggtOld, $ggtNew, $ggtContent)]), true);

if (is_wp_error($ggtResult)) {
	WP_CLI::error($ggtResult->get_error_message());
}

WP_CLI::success("About Us ({$ggtPage->ID}): hero photo set to attachment {$ggtPhoto} (revision kept).");

<?php

/**
 * Homepage: replace the "b2b-photo" placeholder image with the van-loading photo that used to be in the hero.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/home-b2b-photo.php [dry-run]
 *
 * The homepage hero's right column now shows the quote form (FormsSwap, bin/hero-quote-form.php), so its photo,
 * ggt-van-loading-warehouse.jpg, moves down the page into the image block that still shows the grey
 * "PLACEHOLDER · B2B-PHOTO" SVG. Same block, same style (is-style-ggt-rounded ggt-photo, cover); only the
 * attachment, src and alt change. The hero keeps its copy of the photo as the fallback when no form is chosen.
 *
 * Attachments are found by file name, so the script works wherever the IDs differ. Idempotent; saves with
 * wp_update_post (keeps a revision to roll back to).
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);

$ggtAttachment = static function (string $fileStem): int {
	$ids = get_posts([
		'post_type' => 'attachment',
		'post_status' => 'inherit',
		'fields' => 'ids',
		'numberposts' => -1,
		'meta_query' => [
			[
				'key' => '_wp_attached_file',
				'value' => $fileStem,
				'compare' => 'LIKE',
			],
		],
	]);

	return $ids ? (int) $ids[0] : 0;
};

$ggtFront = (int) get_option('page_on_front');
$ggtHome = $ggtFront ? get_post($ggtFront) : null;

if (!$ggtHome) {
	WP_CLI::error('No static front page set.');
}

$ggtPhoto = $ggtAttachment('ggt-van-loading-warehouse');

if (!$ggtPhoto) {
	WP_CLI::error('Attachment ggt-van-loading-warehouse not found.');
}

$ggtContent = $ggtHome->post_content;

// The core/image block whose <img> is the b2b-photo placeholder.
$ggtPattern = '#<!-- wp:image (\{[^}]*\}) -->\s*<figure class="([^"]*)"><img src="[^"]*b2b-photo\.svg" alt="[^"]*" class="wp-image-\d+"([^>]*)/></figure>\s*<!-- /wp:image -->#';

if (!preg_match($ggtPattern, $ggtContent, $ggtMatch)) {
	WP_CLI::log(strpos($ggtContent, 'ggt-van-loading-warehouse') !== false && strpos($ggtContent, 'b2b-photo') === false
		? 'Homepage: placeholder already replaced; skipped.'
		: 'Homepage: no b2b-photo placeholder image block found; skipped.');
	return;
}

$ggtAttrs = json_decode($ggtMatch[1], true);
$ggtAttrs['id'] = $ggtPhoto;

$ggtUrl = (string) wp_get_attachment_url($ggtPhoto);
$ggtAlt = (string) get_post_meta($ggtPhoto, '_wp_attachment_image_alt', true);

$ggtBlock = '<!-- wp:image ' . wp_json_encode($ggtAttrs, JSON_UNESCAPED_SLASHES) . " -->\n"
	. '<figure class="' . $ggtMatch[2] . '"><img src="' . esc_url($ggtUrl) . '" alt="' . esc_attr($ggtAlt) . '" class="wp-image-' . $ggtPhoto . '"' . $ggtMatch[3] . "/></figure>\n"
	. '<!-- /wp:image -->';

$ggtNew = str_replace($ggtMatch[0], $ggtBlock, $ggtContent);

if ($ggtDryRun) {
	WP_CLI::log("[dry-run] Homepage: would replace\n{$ggtMatch[0]}\nwith\n{$ggtBlock}");
	return;
}

$ggtResult = wp_update_post(wp_slash(['ID' => $ggtFront, 'post_content' => $ggtNew]), true);

if (is_wp_error($ggtResult)) {
	WP_CLI::error($ggtResult->get_error_message());
}

WP_CLI::success("Homepage: placeholder replaced with attachment {$ggtPhoto} (revision kept).");

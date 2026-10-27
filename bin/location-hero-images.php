<?php

/**
 * Sideload the location hero photos (2 per city: landmark, downtown) from Wikimedia Commons.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/location-hero-images.php [city=<slug>] [dry-run]
 *
 * Idempotent: an attachment whose `_ggt_hero_key` meta matches (e.g. `austin-landmark`) is skipped.
 * Each attachment stores the Commons file page in `_ggt_source_url`, the license in
 * `_ggt_license` and the author in `_ggt_author`. Prints `<key>\t<attachment id>` per photo.
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$ggtOpts = [];
foreach ($args ?? [] as $ggtArg) {
	[$ggtKey, $ggtVal] = array_pad(explode('=', $ggtArg, 2), 2, true);
	$ggtOpts[$ggtKey] = $ggtVal;
}

$ggtPhotos = require __DIR__ . '/location-hero-photos.php';

foreach ($ggtPhotos as $ggtCity => $ggtSlides) {
	if (isset($ggtOpts['city']) && $ggtOpts['city'] !== $ggtCity) {
		continue;
	}

	foreach ($ggtSlides as $ggtSlide => $ggtPhoto) {
		$ggtHeroKey = "{$ggtCity}-{$ggtSlide}";

		$ggtExisting = get_posts([
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'meta_key' => '_ggt_hero_key',
			'meta_value' => $ggtHeroKey,
			'fields' => 'ids',
			'numberposts' => 1,
		]);

		if ($ggtExisting) {
			WP_CLI::log("{$ggtHeroKey}\t{$ggtExisting[0]}\t(exists)");
			continue;
		}

		$ggtFileUrl = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($ggtPhoto['file']) . '?width=1920';
		$ggtPageUrl = 'https://commons.wikimedia.org/wiki/File:' . str_replace('%2F', '/', rawurlencode(str_replace(' ', '_', $ggtPhoto['file'])));

		if (isset($ggtOpts['dry-run'])) {
			WP_CLI::log("{$ggtHeroKey}\t(dry run)\t{$ggtFileUrl}");
			continue;
		}

		// Commons asks for a descriptive User-Agent; WordPress' default gets throttled.
		$ggtTmp = wp_tempnam($ggtHeroKey);
		$ggtResponse = wp_remote_get($ggtFileUrl, [
			'timeout' => 120,
			'stream' => true,
			'filename' => $ggtTmp,
			'user-agent' => 'GoingGreenTransportSite/1.0 (https://goinggreentransport.com; hero photo import)',
		]);

		if (is_wp_error($ggtResponse) || wp_remote_retrieve_response_code($ggtResponse) !== 200) {
			@unlink($ggtTmp);
			WP_CLI::warning("{$ggtHeroKey}: download failed (" . (is_wp_error($ggtResponse) ? $ggtResponse->get_error_message() : wp_remote_retrieve_response_code($ggtResponse)) . ')');
			continue;
		}

		// Always store a JPEG (the Stillwater bridge is a large PNG on Commons).
		$ggtName = "city-hero-{$ggtCity}-{$ggtSlide}.jpg";
		$ggtMime = wp_get_image_mime($ggtTmp);

		if ($ggtMime !== 'image/jpeg') {
			$ggtEditor = wp_get_image_editor($ggtTmp);

			if (is_wp_error($ggtEditor)) {
				@unlink($ggtTmp);
				WP_CLI::warning("{$ggtHeroKey}: cannot convert {$ggtMime}");
				continue;
			}

			$ggtEditor->set_quality(85);
			$ggtSaved = $ggtEditor->save($ggtTmp . '.jpg', 'image/jpeg');
			@unlink($ggtTmp);
			$ggtTmp = $ggtSaved['path'];
		}

		$ggtId = media_handle_sideload(['name' => $ggtName, 'tmp_name' => $ggtTmp], 0, $ggtPhoto['alt']);

		if (is_wp_error($ggtId)) {
			@unlink($ggtTmp);
			WP_CLI::warning("{$ggtHeroKey}: " . $ggtId->get_error_message());
			continue;
		}

		update_post_meta($ggtId, '_wp_attachment_image_alt', $ggtPhoto['alt']);
		update_post_meta($ggtId, '_ggt_hero_key', $ggtHeroKey);
		update_post_meta($ggtId, '_ggt_source_url', $ggtPageUrl);
		update_post_meta($ggtId, '_ggt_license', $ggtPhoto['license']);
		update_post_meta($ggtId, '_ggt_author', $ggtPhoto['author']);

		WP_CLI::log("{$ggtHeroKey}\t{$ggtId}");
	}
}

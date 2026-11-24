<?php

/**
 * Put the quote form in the header of the homepage.
 *
 * Superseded (2026-11-16) by bin/home-hero-one-column.php (intro card on top, quote form under it); on a hero
 * that is already one column this script finds no `.ggt-clip` group and changes nothing.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/hero-quote-form.php [dry-run]
 *
 * Homepage (page_on_front): the hero's right column group (`.ggt-clip`, the photo) gets the class
 * `ggt-hero-form-slot`. FormsSwap::swapHero then renders the "Homepage hero" form there (Settings > General >
 * Site forms); with "Old form" the photo shows as before.
 *
 * The Get a Quote page needs no content change: its quote embed (`.ggt-sec--quote-form`, the section under the
 * banner) is where FormsSwap::swapQuote renders the "Quote page" form, full width.
 *
 * Idempotent: pages already converted are skipped. Saves with wp_update_post (keeps a revision to roll back to).
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);

$ggtHasClass = static function (array $block, string $class): bool {
	return in_array($class, explode(' ', (string) ($block['attrs']['className'] ?? '')), true);
};

// Depth-first search; returns a reference path (list of indexes) to the first matching block.
$ggtFind = static function (array $blocks, callable $match, array $path = []) use (&$ggtFind): ?array {
	foreach ($blocks as $index => $block) {
		if ($match($block)) {
			return [...$path, $index];
		}

		$found = $ggtFind($block['innerBlocks'] ?? [], $match, [...$path, $index]);
		if ($found !== null) {
			return $found;
		}
	}

	return null;
};

// Get / set a block by path.
$ggtGet = static function (array $blocks, array $path): array {
	$block = ['innerBlocks' => $blocks];
	foreach ($path as $index) {
		$block = $block['innerBlocks'][$index];
	}
	return $block;
};

$ggtSet = static function (array &$blocks, array $path, array $value): void {
	$ref = &$blocks;
	$last = array_pop($path);
	foreach ($path as $index) {
		$ref = &$ref[$index]['innerBlocks'];
	}
	$ref[$last] = $value;
};

$ggtSave = static function (int $postId, array $blocks, string $label) use ($ggtDryRun): void {
	$content = serialize_blocks($blocks);

	if ($ggtDryRun) {
		WP_CLI::log("[dry-run] {$label}: would save post {$postId} (" . strlen($content) . ' bytes).');
		return;
	}

	$result = wp_update_post(wp_slash(['ID' => $postId, 'post_content' => $content]), true);

	if (is_wp_error($result)) {
		WP_CLI::error("{$label}: " . $result->get_error_message());
	}

	WP_CLI::success("{$label}: saved post {$postId} (revision kept).");
};

// ---------------------------------------------------------------------------------------------------------------
// Homepage: mark the hero photo group as the form slot.
// ---------------------------------------------------------------------------------------------------------------
$ggtFront = (int) get_option('page_on_front');
$ggtHome = $ggtFront ? get_post($ggtFront) : null;

if (!$ggtHome) {
	WP_CLI::warning('Homepage: no static front page set; skipped.');
} else {
	$blocks = parse_blocks($ggtHome->post_content);
	$heroPath = $ggtFind($blocks, static fn ($b) => ($b['blockName'] ?? '') === 'core/group' && $ggtHasClass($b, 'ggt-sec--hero'));

	if ($heroPath === null) {
		WP_CLI::warning('Homepage: no .ggt-sec--hero section; skipped.');
	} else {
		$hero = $ggtGet($blocks, $heroPath);
		$slotRel = $ggtFind($hero['innerBlocks'], static fn ($b) => ($b['blockName'] ?? '') === 'core/group' && $ggtHasClass($b, 'ggt-clip'));

		if ($slotRel === null) {
			WP_CLI::warning('Homepage: no .ggt-clip group in the hero; skipped.');
		} else {
			$slotPath = [...$heroPath, ...$slotRel];
			$slot = $ggtGet($blocks, $slotPath);

			if ($ggtHasClass($slot, 'ggt-hero-form-slot')) {
				WP_CLI::log('Homepage: hero slot already marked; skipped.');
			} else {
				$slot['attrs']['className'] = trim(($slot['attrs']['className'] ?? '') . ' ggt-hero-form-slot');
				// Keep the saved markup in step with the attribute (block validation in the editor).
				$slot['innerHTML'] = preg_replace('/\bggt-clip\b/', 'ggt-clip ggt-hero-form-slot', $slot['innerHTML'], 1);
				$slot['innerContent'][0] = preg_replace('/\bggt-clip\b/', 'ggt-clip ggt-hero-form-slot', $slot['innerContent'][0], 1);

				$ggtSet($blocks, $slotPath, $slot);
				$ggtSave($ggtFront, $blocks, 'Homepage');
			}
		}
	}
}

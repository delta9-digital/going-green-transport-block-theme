<?php

/**
 * Homepage hero (one column, bin/home-hero-one-column.php): full-width body copy, and the "Get a Free Quote"
 * button jumps to the form.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/home-hero-copy-cta.php [dry-run]
 *
 * In the lime intro card:
 * - the body copy's group drops its 580px content width (layout constrained -> default), so the copy runs the
 *   width of the card;
 * - the "Get a Free Quote" split button links to `#hero-quote`, the anchor this script puts on the `.ggt-hero-quote`
 *   group (the form), instead of /get-a-quote/, with the standard up-right arrow (an earlier version added the
 *   down-right `ggt-arrow-down` class; it is taken off).
 *
 * Each part is skipped when already done. Saves with wp_update_post (keeps a revision to roll back to).
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);

const GGT_HERO_FORM_ANCHOR = 'hero-quote';

$ggtFront = (int) get_option('page_on_front');
$ggtHome = $ggtFront ? get_post($ggtFront) : null;

if (!$ggtHome) {
	WP_CLI::error('No static front page set.');
}

$blocks = parse_blocks($ggtHome->post_content);

$heroIndex = null;
foreach ($blocks as $i => $block) {
	if (($block['blockName'] ?? '') === 'core/group' && in_array('ggt-sec--hero', explode(' ', (string) ($block['attrs']['className'] ?? '')), true)) {
		$heroIndex = $i;
		break;
	}
}

if ($heroIndex === null || !preg_match('/ggt-hero-(stack|cols)/', serialize_block($blocks[$heroIndex]))) {
	WP_CLI::error('Homepage: hero has no .ggt-hero-quote layout; run bin/home-hero-one-column.php first.');
}

$done = [];

$walk = static function (array &$list) use (&$walk, &$done): void {
	foreach ($list as &$block) {
		$name = $block['blockName'] ?? '';
		$classes = explode(' ', (string) ($block['attrs']['className'] ?? ''));

		// Body copy: the constrained 580px group around the intro paragraph.
		if ($name === 'core/group' && ($block['attrs']['layout']['contentSize'] ?? '') === '580px' && strpos(serialize_block($block), 'franchise') !== false) {
			$block['attrs']['layout'] = ['type' => 'default'];
			$done[] = 'body copy full width';
		}

		// The form group: anchor.
		if ($name === 'core/group' && in_array('ggt-hero-quote', $classes, true) && strpos($block['innerContent'][0], 'id="' . GGT_HERO_FORM_ANCHOR . '"') === false) {
			$block['attrs']['anchor'] = GGT_HERO_FORM_ANCHOR;
			$addId = static fn (string $html): string => (string) preg_replace('/^(\s*<div class="[^"]*")(?! id=)/', '$1 id="' . GGT_HERO_FORM_ANCHOR . '"', $html, 1);
			$block['innerHTML'] = $addId($block['innerHTML']);
			$block['innerContent'][0] = $addId($block['innerContent'][0]);
			$done[] = 'anchor #' . GGT_HERO_FORM_ANCHOR . ' on the form';
		}

		// Get a Free Quote: links to the form beside / under the card, with the standard up-right arrow (the down-right
		// ggt-arrow-down class from the one-column hero is taken off again).
		if ($name === 'core/button' && strpos($block['innerHTML'], '>Get a Free Quote<') !== false
			&& (in_array('ggt-arrow-down', $classes, true) || strpos($block['innerHTML'], 'href="#' . GGT_HERO_FORM_ANCHOR . '"') === false)) {
			$rest = array_values(array_filter($classes, static fn ($class) => $class !== '' && $class !== 'ggt-arrow-down'));
			$block['attrs']['className'] = implode(' ', $rest);
			$fix = static function (string $html): string {
				$html = str_replace(' ggt-arrow-down"', '"', $html);
				return (string) preg_replace('/href="[^"]*"/', 'href="#' . GGT_HERO_FORM_ANCHOR . '"', $html, 1);
			};
			$block['innerHTML'] = $fix($block['innerHTML']);
			$block['innerContent'] = array_map(static fn ($part) => is_string($part) ? $fix($part) : $part, $block['innerContent']);
			$done[] = 'Get a Free Quote: up-right arrow, links to the form';
		}

		if (!empty($block['innerBlocks'])) {
			$walk($block['innerBlocks']);
		}
	}
	unset($block);
};
$walk($blocks[$heroIndex]['innerBlocks']);

if (!$done) {
	WP_CLI::log('Homepage: hero copy and CTA already updated; skipped.');
	return;
}

$content = serialize_blocks($blocks);

if (serialize_blocks(parse_blocks($content)) !== $content) {
	WP_CLI::error('Homepage: new content does not round-trip through the block parser; nothing changed.');
}

if ($ggtDryRun) {
	WP_CLI::log(sprintf('[dry-run] Homepage (%d): %s.', $ggtFront, implode('; ', $done)));
	return;
}

$result = wp_update_post(wp_slash(['ID' => $ggtFront, 'post_content' => $content]), true);

if (is_wp_error($result)) {
	WP_CLI::error($result->get_error_message());
}

WP_CLI::success(sprintf('Homepage (%d): %s (revision kept).', $ggtFront, implode('; ', $done)));

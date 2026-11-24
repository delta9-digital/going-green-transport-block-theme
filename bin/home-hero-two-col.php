<?php

/**
 * Homepage hero: two columns, the "Moving Cannabis Forward." card in a third and the quote form in two thirds.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/home-hero-two-col.php [dry-run]
 *
 * Turns the one-column hero stack (`.ggt-hero-stack`, bin/home-hero-one-column.php: the lime card, then the
 * `.ggt-hero-quote` form group) into a core/columns block `.ggt-hero-cols`: column 1 (33.33%) the lime card, column 2
 * (66.66%) the form group, unchanged (anchor #hero-quote, FormsSwap renders the "Quote page" form there). The theme
 * sizes the heading for the narrow column, keeps the card in view beside the taller form, and stacks the columns
 * below 1200px (_sections.scss).
 *
 * Idempotent; saves with wp_update_post (keeps a revision to roll back to).
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);

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

if ($heroIndex === null) {
	WP_CLI::error('Homepage: no .ggt-sec--hero section.');
}

$hero = &$blocks[$heroIndex];

if (strpos(serialize_block($hero), 'ggt-hero-cols') !== false) {
	WP_CLI::log('Homepage: hero already two columns (1/3 + 2/3); skipped.');
	return;
}

$stackIndex = null;
foreach ($hero['innerBlocks'] as $j => $block) {
	if (in_array('ggt-hero-stack', explode(' ', (string) ($block['attrs']['className'] ?? '')), true)) {
		$stackIndex = $j;
		break;
	}
}

if ($stackIndex === null) {
	WP_CLI::error('Homepage: the hero is not the one-column stack (.ggt-hero-stack); run bin/home-hero-one-column.php first.');
}

$stack = $hero['innerBlocks'][$stackIndex];
$intro = null;
$quote = null;

foreach ($stack['innerBlocks'] as $block) {
	if (($block['attrs']['backgroundColor'] ?? '') === 'lime-500') {
		$intro = $block;
	} elseif (in_array('ggt-hero-quote', explode(' ', (string) ($block['attrs']['className'] ?? '')), true)) {
		$quote = $block;
	}
}

if (!$intro || !$quote) {
	WP_CLI::error('Homepage: hero stack has no lime card or no .ggt-hero-quote group; nothing changed.');
}

$markup = '<!-- wp:columns {"align":"wide","className":"ggt-hero-cols","style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"metadata":{"name":"Hero: intro and quote form"}} -->' . "\n"
	. '<div class="wp-block-columns alignwide ggt-hero-cols"><!-- wp:column {"width":"33.33%","style":{"spacing":{"blockGap":"0"}}} -->' . "\n"
	. '<div class="wp-block-column" style="flex-basis:33.33%">' . serialize_block($intro) . '</div>' . "\n"
	. '<!-- /wp:column -->' . "\n\n"
	. '<!-- wp:column {"width":"66.66%","style":{"spacing":{"blockGap":"0"}}} -->' . "\n"
	. '<div class="wp-block-column" style="flex-basis:66.66%">' . serialize_block($quote) . '</div>' . "\n"
	. '<!-- /wp:column --></div>' . "\n"
	. '<!-- /wp:columns -->';

$columns = array_values(array_filter(parse_blocks($markup), static fn ($b) => !empty($b['blockName'])))[0];

if (serialize_block($columns) !== $markup) {
	WP_CLI::error('Homepage: new hero markup does not round-trip through the block parser; nothing changed.');
}

$hero['innerBlocks'][$stackIndex] = $columns;
unset($hero);

$content = serialize_blocks($blocks);

if ($ggtDryRun) {
	WP_CLI::log(sprintf('[dry-run] Homepage (%d): hero -> two columns (card 1/3, form 2/3); %d -> %d bytes.', $ggtFront, strlen($ggtHome->post_content), strlen($content)));
	return;
}

$result = wp_update_post(wp_slash(['ID' => $ggtFront, 'post_content' => $content]), true);

if (is_wp_error($result)) {
	WP_CLI::error($result->get_error_message());
}

WP_CLI::success(sprintf('Homepage (%d): hero is two columns, card 1/3 and form 2/3 (revision kept).', $ggtFront));

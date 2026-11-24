<?php

/**
 * Homepage hero: one column, "Moving Cannabis Forward." card on top and the quote form under it.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/home-hero-one-column.php [dry-run]
 *
 * The hero (`.ggt-sec--hero` on the front page) was a two-column core/columns block: the lime intro card left and,
 * right, either the hero form slot (`.ggt-hero-form-slot`, the van photo; bin/hero-quote-form.php) or a
 * `.ggt-hero-quote` group with the app quote embed. It becomes a wide stack (`.ggt-hero-stack`): the lime card,
 * unchanged, then the `.ggt-hero-quote` group with the quote embed block, which FormsSwap::swapQuote renders as the
 * "Quote page" form in a full-width white card (the app iframe when no form is chosen). The checkmark strip under the
 * hero stays as it is.
 *
 * Supersedes the homepage part of bin/hero-quote-form.php; followed by bin/home-hero-two-col.php (card 1/3, form 2/3). Idempotent; saves with wp_update_post (keeps a revision).
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

if (strpos(serialize_block($hero), 'ggt-hero-stack') !== false || strpos(serialize_block($hero), 'ggt-hero-cols') !== false) {
	WP_CLI::log('Homepage: hero already converted (one column, or the later 1/3 + 2/3 columns); skipped.');
	return;
}

$columnsIndex = null;
foreach ($hero['innerBlocks'] as $j => $block) {
	if (($block['blockName'] ?? '') === 'core/columns') {
		$columnsIndex = $j;
		break;
	}
}

if ($columnsIndex === null || count($hero['innerBlocks'][$columnsIndex]['innerBlocks']) < 2) {
	WP_CLI::error('Homepage: the hero has no two-column block; nothing changed.');
}

$columns = $hero['innerBlocks'][$columnsIndex];
$intro = $columns['innerBlocks'][0]['innerBlocks'][0] ?? null;

if (!$intro || ($intro['attrs']['backgroundColor'] ?? '') !== 'lime-500' || strpos(serialize_block($intro), 'Moving Cannabis') === false) {
	WP_CLI::error('Homepage: first hero column is not the lime "Moving Cannabis Forward." card; nothing changed.');
}

// The quote group: reuse the existing .ggt-hero-quote (local), otherwise a new one with the app embed.
$quote = null;
foreach ($columns['innerBlocks'][1]['innerBlocks'] as $block) {
	if (in_array('ggt-hero-quote', explode(' ', (string) ($block['attrs']['className'] ?? '')), true)) {
		$quote = serialize_block($block);
	}
}

if ($quote === null) {
	$quote = '<!-- wp:group {"className":"ggt-hero-quote","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"},"metadata":{"name":"Quote form"}} -->' . "\n"
		. '<div class="wp-block-group ggt-hero-quote"><!-- wp:eightshift-boilerplate/embed-frame {"wrapperUse":false,"embedFrameUrl":"https://app.goinggreentransport.com/quote","embedFrameTitle":"Request a transport quote","embedFrameMinHeight":900,"embedFrameResizeMessage":"ggt-resize","embedFrameEventMessage":"ggt-event","embedFrameEventPrefix":"ggt_","embedFrameEventKey":"ggt","embedFrameLazy":false} /--></div>' . "\n"
		. '<!-- /wp:group -->';
}

$stackMarkup = '<!-- wp:group {"align":"wide","className":"ggt-hero-stack","style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"default"},"metadata":{"name":"Hero: intro and quote form"}} -->' . "\n"
	. '<div class="wp-block-group alignwide ggt-hero-stack">' . serialize_block($intro) . "\n\n" . $quote . '</div>' . "\n"
	. '<!-- /wp:group -->';

$stack = array_values(array_filter(parse_blocks($stackMarkup), static fn ($b) => !empty($b['blockName'])))[0];

if (serialize_block($stack) !== $stackMarkup) {
	WP_CLI::error('Homepage: new hero markup does not round-trip through the block parser; nothing changed.');
}

$hero['innerBlocks'][$columnsIndex] = $stack;
unset($hero);

$content = serialize_blocks($blocks);

if ($ggtDryRun) {
	WP_CLI::log(sprintf('[dry-run] Homepage (%d): hero -> one column (intro card, then quote form); %d -> %d bytes.', $ggtFront, strlen($ggtHome->post_content), strlen($content)));
	return;
}

$result = wp_update_post(wp_slash(['ID' => $ggtFront, 'post_content' => $content]), true);

if (is_wp_error($result)) {
	WP_CLI::error($result->get_error_message());
}

WP_CLI::success(sprintf('Homepage (%d): hero is one column, intro card then quote form (revision kept).', $ggtFront));

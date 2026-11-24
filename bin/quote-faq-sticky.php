<?php

/**
 * Get a Quote page: "Quote Questions" FAQ in two columns, the intro sticky while the questions scroll.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/quote-faq-sticky.php [dry-run]
 *
 * FAQ section (`.ggt-sec--faq`):
 * - Layout: back to the two-column grid every FAQ section uses (intro left, accordion right).
 * - Intro (left column, `.ggt-sticky`: stays in view on desktop while the questions scroll past): the page's own
 *   "Quote Questions" heading again, with the homepage FAQ's structure around it (label, short text, Contact Us,
 *   which jumps to the Prefer to Talk card (#contact), and View Full FAQ).
 * - Questions: the six quote questions stay; five more are added from the site's own FAQ copy (homepage FAQ and the
 *   FAQ page): licensed and insured, areas served, Metrc, testing labs, delays.
 *
 * Replaces bin/quote-faq-full-width.php (stacked) and the FAQ header part of bin/quote-page-sections.php.
 * Each part is skipped when already done. Saves with wp_update_post (keeps a revision to roll back to).
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);

$ggtPage = get_page_by_path('get-a-quote');
$ggtFaqPage = get_page_by_path('cannabis-transport-faq');

if (!$ggtPage) {
	WP_CLI::error('No page with the slug get-a-quote.');
}

$ggtFaqUrl = $ggtFaqPage ? wp_make_link_relative(get_permalink($ggtFaqPage)) : '/cannabis-transport-faq/';

$blocks = parse_blocks($ggtPage->post_content);
$done = [];

$faqIndex = null;
foreach ($blocks as $i => $block) {
	if (($block['blockName'] ?? '') === 'core/group' && in_array('ggt-sec--faq', explode(' ', (string) ($block['attrs']['className'] ?? '')), true)) {
		$faqIndex = $i;
	}
}

if ($faqIndex === null) {
	WP_CLI::error('Get a Quote: no .ggt-sec--faq section.');
}

$wideIndex = null;
foreach ($blocks[$faqIndex]['innerBlocks'] as $j => $block) {
	if (($block['blockName'] ?? '') === 'core/group' && ($block['attrs']['align'] ?? '') === 'wide') {
		$wideIndex = $j;
	}
}

if ($wideIndex === null) {
	WP_CLI::error('Get a Quote: FAQ section has no wide group; nothing changed.');
}

$wide = &$blocks[$faqIndex]['innerBlocks'][$wideIndex];

// --- 1. Two columns ----------------------------------------------------------------------------------------------
if (($wide['attrs']['layout']['type'] ?? '') !== 'grid') {
	$wide['attrs']['layout'] = ['type' => 'grid', 'minimumColumnWidth' => '320px', 'columnCount' => 2];
	$wide['attrs']['style']['spacing']['blockGap'] = 'var:preset|spacing|120';
	$done[] = 'two columns';
}

// --- 2. Sticky intro ---------------------------------------------------------------------------------------------
$introIndex = null;
$accordionIndex = null;
foreach ($wide['innerBlocks'] as $k => $block) {
	if (($block['blockName'] ?? '') === 'core/accordion') {
		$accordionIndex = $k;
	} elseif ($introIndex === null && ($block['blockName'] ?? '') === 'core/group') {
		$introIndex = $k;
	}
}

if ($introIndex === null || $accordionIndex === null) {
	WP_CLI::error('Get a Quote: FAQ has no intro group or accordion; nothing changed.');
}

if (!in_array('ggt-sticky', explode(' ', (string) ($wide['innerBlocks'][$introIndex]['attrs']['className'] ?? '')), true)) {
	$faqHref = esc_attr($ggtFaqUrl);
	$intro = <<<HTML
<!-- wp:group {"className":"ggt-sticky","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group ggt-sticky"><!-- wp:paragraph {"textColor":"gray-600","fontFamily":"heading","fontSize":"small","className":"is-style-ggt-label","style":{"typography":{"fontWeight":"600","letterSpacing":"0.12em","textTransform":"uppercase"},"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}}} -->
<p class="is-style-ggt-label has-gray-600-color has-text-color has-heading-font-family has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--30);font-weight:600;letter-spacing:0.12em;text-transform:uppercase">Before You Book</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textColor":"green-900","fontSize":"section-md","style":{"typography":{"letterSpacing":"-0.02em","lineHeight":"1.05"},"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} -->
<h2 class="wp-block-heading has-green-900-color has-text-color has-section-md-font-size" style="margin-bottom:var(--wp--preset--spacing--40);letter-spacing:-0.02em;line-height:1.05">Quote <mark style="background-color:rgba(0,0,0,0)" class="has-inline-color has-green-500-color">Questions.</mark></h2>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|70"},"blockGap":"0"}},"layout":{"type":"constrained","contentSize":"460px","justifyContent":"left"}} -->
<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--70)"><!-- wp:paragraph {"textColor":"ink-muted","fontSize":"lead"} -->
<p class="has-ink-muted-color has-text-color has-lead-font-size">Pricing, timing, and what to have ready for your first run. Still have a question? Our dispatch team is happy to talk it through.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-ggt-split"} -->
<div class="wp-block-button is-style-ggt-split"><a class="wp-block-button__link wp-element-button" href="#contact">Contact Us</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-ggt-text"} -->
<div class="wp-block-button is-style-ggt-text"><a class="wp-block-button__link wp-element-button" href="{$faqHref}">View Full FAQ</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
HTML;

	$parsed = array_values(array_filter(parse_blocks($intro), static fn ($b) => !empty($b['blockName'])));
	$wide['innerBlocks'][$introIndex] = $parsed[0];
	$done[] = 'sticky Quote Questions intro';
}

// --- 3. More questions -------------------------------------------------------------------------------------------
$ggtMore = [
	['Is Going Green Transport licensed and insured?', '<p>Yes. Going Green Transport LLC is a state-certified Minnesota cannabis transport agent, fully licensed, insured, and bonded to transport cannabis products between licensed facilities within the state of Minnesota.</p>'],
	['What areas of Minnesota do you serve?', '<p>We provide statewide cannabis transport throughout Minnesota, including Minneapolis, St. Paul, Rochester, Duluth, St. Cloud, Mankato, Moorhead, Brainerd, Bemidji, and 20+ additional cities and regions. If you’re a licensed operator in Minnesota, we can move your product.</p>'],
	['How do you handle Metrc compliance?', '<p>Every transport we complete is Metrc-aligned from the start. We verify manifests against physical package counts at pickup, maintain documented chain-of-custody throughout transit, and provide confirmed delivery aligned with Minnesota’s Metrc track-and-trace system.</p>'],
	['Can you transport to or from testing labs?', '<p>Yes. We regularly transport samples and full product batches to licensed testing facilities in accordance with OCM procedures.</p>'],
	['What happens if there is a delay?', '<p>If delays occur due to traffic, weather, or regulatory issues, we notify both sender and recipient immediately and provide updated ETAs.</p>'],
];

$accordion = &$wide['innerBlocks'][$accordionIndex];
$existing = serialize_block($accordion);
$added = 0;

foreach ($ggtMore as [$question, $answer]) {
	if (strpos($existing, '>' . $question . '<') !== false) {
		continue;
	}

	$item = <<<HTML
<!-- wp:accordion-item -->
<div class="wp-block-accordion-item"><!-- wp:accordion-heading -->
<h3 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">{$question}</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->

<!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel"><!-- wp:paragraph -->
{$answer}
<!-- /wp:paragraph --></div>
<!-- /wp:accordion-panel --></div>
<!-- /wp:accordion-item -->
HTML;

	$parsed = array_values(array_filter(parse_blocks($item), static fn ($b) => !empty($b['blockName'])));
	$close = array_pop($accordion['innerContent']);
	array_push($accordion['innerContent'], "\n\n", null, $close);
	$accordion['innerBlocks'][] = $parsed[0];
	$added++;
}
unset($accordion, $wide);

if ($added) {
	$done[] = "{$added} more questions";
}

if (!$done) {
	WP_CLI::log('Get a Quote: FAQ already sticky two-column with the extra questions; skipped.');
	return;
}

$content = serialize_blocks($blocks);

if (serialize_blocks(parse_blocks($content)) !== $content) {
	WP_CLI::error('Get a Quote: new content does not round-trip through the block parser; nothing changed.');
}

if ($ggtDryRun) {
	WP_CLI::log(sprintf('[dry-run] Get a Quote (%d): %s (%d -> %d bytes).', $ggtPage->ID, implode('; ', $done), strlen($ggtPage->post_content), strlen($content)));
	return;
}

$result = wp_update_post(wp_slash(['ID' => $ggtPage->ID, 'post_content' => $content]), true);

if (is_wp_error($result)) {
	WP_CLI::error($result->get_error_message());
}

WP_CLI::success(sprintf('Get a Quote (%d): %s (revision kept).', $ggtPage->ID, implode('; ', $done)));

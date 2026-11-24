<?php

/**
 * Get a Quote page: "What Happens Next" laid out like the About page's "Our Team" section.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/quote-next-like-team.php [dry-run]
 *
 * Our Team (About Us, `.ggt-sec--team`): a two-column grid; the left column is sticky (`.ggt-sticky`) with the
 * label and heading in `.ggt-col-start` (assets/scripts/col-start starts the right column level with the text under
 * the heading) and the intro under it; the right column is a list of numbered rows between thin gray rules
 * (number 64px wide, heading-lg green-800; title heading-lg; text body-md, 560px).
 *
 * What Happens Next (`.ggt-sec--quote-next`) gets the same markup: label + "From Quote to Delivered." heading
 * (Our Team's size) in the sticky column with the intro and the "Have these ready" card under them; the three steps
 * as Our Team rows on the right. Every text is taken from the current section, so edits made in the editor carry
 * over. Idempotent (skips a section that already has `.ggt-sticky`); saves with wp_update_post (keeps a revision).
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);

$ggtPage = get_page_by_path('get-a-quote');

if (!$ggtPage) {
	WP_CLI::error('No page with the slug get-a-quote.');
}

$ggtBlocks = parse_blocks($ggtPage->post_content);

$ggtIndex = null;
foreach ($ggtBlocks as $i => $block) {
	if (($block['blockName'] ?? '') === 'core/group' && in_array('ggt-sec--quote-next', explode(' ', (string) ($block['attrs']['className'] ?? '')), true)) {
		$ggtIndex = $i;
	}
}

if ($ggtIndex === null) {
	WP_CLI::error('Get a Quote: no .ggt-sec--quote-next section.');
}

$ggtSection = $ggtBlocks[$ggtIndex];

if (strpos(serialize_block($ggtSection), 'ggt-sticky') !== false) {
	WP_CLI::log('Get a Quote: What Happens Next already uses the Our Team layout; skipped.');
	return;
}

// All blocks of the section, depth first.
$ggtAll = [];
$ggtWalk = static function (array $blocks) use (&$ggtWalk, &$ggtAll): void {
	foreach ($blocks as $block) {
		if (!empty($block['blockName'])) {
			$ggtAll[] = $block;
		}
		$ggtWalk($block['innerBlocks'] ?? []);
	}
};
$ggtWalk([$ggtSection]);

// Inner HTML of a block's single top-level element (<p>…</p>, <h2>…</h2>).
$ggtInner = static function (array $block): string {
	return trim((string) preg_replace('#^\s*<(\w+)[^>]*>(.*)</\1>\s*$#s', '$2', $block['innerHTML']));
};

$ggtLabel = null;
$ggtHeading = null;
$ggtIntro = null;
$ggtCard = null;
$ggtSteps = [];

foreach ($ggtAll as $block) {
	$name = $block['blockName'];
	$class = (string) ($block['attrs']['className'] ?? '');

	if ($name === 'core/paragraph' && $ggtLabel === null && strpos($class, 'is-style-ggt-label') !== false) {
		$ggtLabel = $ggtInner($block);
	} elseif ($name === 'core/heading' && ($block['attrs']['level'] ?? 2) === 2 && $ggtHeading === null) {
		$ggtHeading = $ggtInner($block);
	} elseif ($name === 'core/paragraph' && $ggtIntro === null && $ggtHeading !== null && ($block['attrs']['fontSize'] ?? '') === 'lead') {
		$ggtIntro = $ggtInner($block);
	} elseif ($name === 'core/group' && $ggtCard === null && ($block['attrs']['backgroundColor'] ?? '') === 'white') {
		$ggtCard = $block;
	} elseif ($name === 'core/columns') {
		$texts = [];
		foreach ($block['innerBlocks'] as $column) {
			foreach ($column['innerBlocks'] as $inner) {
				$texts[] = [$inner['blockName'], $ggtInner($inner)];
			}
		}
		// [number paragraph, h3, text paragraph]
		if (count($texts) === 3 && $texts[1][0] === 'core/heading') {
			$ggtSteps[] = ['num' => $texts[0][1], 'title' => $texts[1][1], 'text' => $texts[2][1]];
		}
	}
}

if ($ggtLabel === null || $ggtHeading === null || $ggtIntro === null || $ggtCard === null || count($ggtSteps) < 1) {
	WP_CLI::error('Get a Quote: What Happens Next does not have the expected label, heading, intro, card and steps; nothing changed.');
}

// --- Markup (Our Team's, block for block) ----------------------------------------------------------------------

$ggtRow = static fn (array $step): string => <<<HTML
<!-- wp:columns {"isStackedOnMobile":false,"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|20","left":"var:preset|spacing|40"},"padding":{"top":"36px","bottom":"36px"}},"border":{"bottom":{"width":"1px","style":"solid","color":"#a3a5a4"}}}} -->
<div class="wp-block-columns is-not-stacked-on-mobile" style="border-bottom-color:#a3a5a4;border-bottom-style:solid;border-bottom-width:1px;padding-top:36px;padding-bottom:36px"><!-- wp:column {"width":"64px","style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-column" style="flex-basis:64px"><!-- wp:paragraph {"textColor":"green-800","fontFamily":"heading","fontSize":"heading-lg","style":{"typography":{"fontWeight":"600","lineHeight":"1.2"}}} -->
<p class="has-green-800-color has-text-color has-heading-font-family has-heading-lg-font-size" style="font-weight:600;line-height:1.2">{$step['num']}</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"textColor":"green-900","fontSize":"heading-lg","style":{"typography":{"letterSpacing":"-0.01em"},"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}}} -->
<h3 class="wp-block-heading has-green-900-color has-text-color has-heading-lg-font-size" style="margin-bottom:var(--wp--preset--spacing--30);letter-spacing:-0.01em">{$step['title']}</h3>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained","contentSize":"560px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"textColor":"ink-muted","fontSize":"body-md","style":{"typography":{"lineHeight":"1.6"}}} -->
<p class="has-ink-muted-color has-text-color has-body-md-font-size" style="line-height:1.6">{$step['text']}</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
HTML;

$ggtRows = implode("\n\n", array_map($ggtRow, $ggtSteps));
$ggtCardMarkup = serialize_block($ggtCard);

$ggtNew = <<<HTML
<!-- wp:group {"tagName":"section","align":"full","className":"ggt-sec ggt-sec\\u002d\\u002dquote-next","style":{"spacing":{"margin":{"top":"0"},"padding":{"top":"var:preset|spacing|140"},"blockGap":"0"}},"layout":{"type":"constrained"},"metadata":{"name":"What Happens Next"}} -->
<section class="wp-block-group alignfull ggt-sec ggt-sec--quote-next" style="margin-top:0;padding-top:var(--wp--preset--spacing--140)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|120"}},"layout":{"type":"grid","minimumColumnWidth":"320px","columnCount":2}} -->
<div class="wp-block-group"><!-- wp:group {"className":"ggt-sticky","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group ggt-sticky"><!-- wp:group {"className":"ggt-col-start","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group ggt-col-start"><!-- wp:paragraph {"textColor":"gray-600","fontFamily":"heading","fontSize":"small","className":"is-style-ggt-label","style":{"typography":{"fontWeight":"600","letterSpacing":"0.12em","textTransform":"uppercase"},"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}}} -->
<p class="is-style-ggt-label has-gray-600-color has-text-color has-heading-font-family has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--30);font-weight:600;letter-spacing:0.12em;text-transform:uppercase">{$ggtLabel}</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textColor":"green-900","style":{"typography":{"fontSize":"clamp(36px, 4.6vw, 64px)","letterSpacing":"-0.02em","lineHeight":"1.02"},"spacing":{"margin":{"bottom":"var:preset|spacing|90"}}}} -->
<h2 class="wp-block-heading has-green-900-color has-text-color" style="margin-bottom:var(--wp--preset--spacing--90);font-size:clamp(36px, 4.6vw, 64px);letter-spacing:-0.02em;line-height:1.02">{$ggtHeading}</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained","contentSize":"460px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"textColor":"ink-muted","fontSize":"lead"} -->
<p class="has-ink-muted-color has-text-color has-lead-font-size">{$ggtIntro}</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|80"},"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--80)">{$ggtCardMarkup}</div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"0"},"border":{"top":{"width":"1px","style":"solid","color":"#a3a5a4"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="border-top-color:#a3a5a4;border-top-style:solid;border-top-width:1px">{$ggtRows}</div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
HTML;

// The new section must survive the block parser unchanged (valid, editable markup).
$ggtParsed = parse_blocks($ggtNew);
$ggtParsed = array_values(array_filter($ggtParsed, static fn ($b) => !empty($b['blockName'])));

if (count($ggtParsed) !== 1 || serialize_block($ggtParsed[0]) !== $ggtNew) {
	WP_CLI::error('Get a Quote: generated section does not round-trip through the block parser; nothing changed.');
}

$ggtOld = serialize_block($ggtSection);

if (substr_count($ggtPage->post_content, $ggtOld) !== 1) {
	WP_CLI::error('Get a Quote: could not locate the What Happens Next markup exactly once; nothing changed.');
}

$ggtContent = str_replace($ggtOld, $ggtNew, $ggtPage->post_content);

if ($ggtDryRun) {
	WP_CLI::log(sprintf('[dry-run] Get a Quote (%d): would rebuild What Happens Next (%d steps, label "%s"); %d -> %d bytes.', $ggtPage->ID, count($ggtSteps), $ggtLabel, strlen($ggtPage->post_content), strlen($ggtContent)));
	return;
}

$ggtResult = wp_update_post(wp_slash(['ID' => $ggtPage->ID, 'post_content' => $ggtContent]), true);

if (is_wp_error($ggtResult)) {
	WP_CLI::error($ggtResult->get_error_message());
}

WP_CLI::success(sprintf('Get a Quote (%d): What Happens Next now uses the Our Team layout (%d steps; revision kept).', $ggtPage->ID, count($ggtSteps)));

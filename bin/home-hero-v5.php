<?php

/**
 * Homepage hero v5: intro column (badge, pills, H1, copy, rating, Google review carousel, CTAs) beside the quote form
 * card, on the page grey with a faint Minnesota outline behind the intro (handoff: ggt-hero-v5-handoff.md).
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/home-hero-v5.php [dry-run] --user=<admin id>
 *
 * Replaces the homepage `.ggt-sec--hero` section, whichever earlier version it is (photo slot, one column, or
 * bin/home-hero-two-col.php), including the white trust strip under it (its labels are now the hero pills). The
 * section keeps its anchor (#top) and classes; it gains `ggt-hero-v5`. The right column is a `.ggt-hero-form-slot`
 * group: FormsSwap::swapHero renders the form chosen under Settings > General > Site forms > Homepage hero there
 * (with "Old form", the slot's own fallback link to the quote page shows). Styles: _hero.scss; carousel and success
 * panel: assets/scripts/hero-v5.
 *
 * Reviews are real Google reviews already on the site (homepage testimonials). Add or swap one by editing the quotes
 * inside the "Google reviews" group in the editor; each quote's citation holds the initial, name and source.
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

// Already v5: only make sure the form card group carries the core Shadow setting (preset "Card"), which earlier
// runs of this script did not set.
if (in_array('ggt-hero-v5', explode(' ', (string) ($blocks[$heroIndex]['attrs']['className'] ?? '')), true)) {
	$ggtShadowed = false;
	$ggtAddShadow = static function (array &$list) use (&$ggtAddShadow, &$ggtShadowed): void {
		foreach ($list as &$block) {
			$classes = explode(' ', (string) ($block['attrs']['className'] ?? ''));

			if (($block['blockName'] ?? '') === 'core/group' && in_array('ggt-hero-form-slot', $classes, true)) {
				if (!empty($block['attrs']['style']['shadow'])) {
					return;
				}

				$block['attrs']['style']['shadow'] = 'var:preset|shadow|card';
				$css = 'box-shadow:var(--wp--preset--shadow--card)';
				$open = static function (string $html) use ($css): string {
					if (preg_match('/^(\s*<div\b[^>]*?)style="([^"]*)"/', $html)) {
						return preg_replace('/^(\s*<div\b[^>]*?)style="([^"]*)"/', '$1style="$2;' . $css . '"', $html, 1);
					}

					return preg_replace('/^(\s*<div\b[^>]*?)>/', '$1 style="' . $css . '">', $html, 1);
				};
				$block['innerHTML'] = $open($block['innerHTML']);
				$block['innerContent'][0] = $open((string) $block['innerContent'][0]);
				$ggtShadowed = true;
				return;
			}

			if (!empty($block['innerBlocks'])) {
				$ggtAddShadow($block['innerBlocks']);
			}
		}
	};

	$ggtAddShadow($blocks[$heroIndex]['innerBlocks']);

	if (!$ggtShadowed) {
		WP_CLI::log('Homepage: hero is already v5 (card shadow already set); skipped.');
		return;
	}

	if ($ggtDryRun) {
		WP_CLI::success('Dry run: the form card group would get the Card shadow. Nothing saved.');
		return;
	}

	$result = wp_update_post(wp_slash(['ID' => $ggtFront, 'post_content' => serialize_blocks($blocks)]), true);

	if (is_wp_error($result)) {
		WP_CLI::error($result->get_error_message());
	}

	WP_CLI::success(sprintf('Homepage (%d): form card group now uses the core Shadow setting, preset Card (revision kept).', $ggtFront));
	return;
}

$ggtQuoteUrl = '/get-a-quote/';

$ggtReviews = [
	[
		'name' => 'Josh',
		'quote' => 'Great Transport 👍 company, I had an awesome driver and will definitely do more business with Going Green Transport!!! Keep up the good work!',
	],
	[
		'name' => 'Danielle D.',
		'quote' => 'Woman owned, totally transparent, and communication proved priority. This company earned their 5 stars and then some!',
	],
	[
		'name' => 'Zack S.',
		'quote' => 'Amazing service great communication from pick up to delivery!',
	],
];

$ggtReviewMarkup = '';
foreach ($ggtReviews as $review) {
	$initial = function_exists('mb_substr') ? mb_substr($review['name'], 0, 1) : substr($review['name'], 0, 1);
	$ggtReviewMarkup .= "<!-- wp:quote {\"className\":\"ggt-review\"} -->\n"
		. "<blockquote class=\"wp-block-quote ggt-review\"><!-- wp:paragraph -->\n"
		. '<p>' . esc_html($review['quote']) . "</p>\n"
		. "<!-- /wp:paragraph --><cite>"
		. '<span class="ggt-review__avatar" aria-hidden="true">' . esc_html($initial) . '</span>'
		. '<span class="ggt-review__name">' . esc_html($review['name']) . '</span>'
		. '<span class="ggt-review__source">Google review · <span class="ggt-review__stars" role="img" aria-label="5 out of 5 stars">★★★★★</span></span>'
		. "</cite></blockquote>\n"
		. "<!-- /wp:quote -->\n\n";
}

$ggtPills = '';
foreach (['Woman-Owned', 'Minority-Led', 'Family-Run'] as $pill) {
	$ggtPills .= "<!-- wp:list-item -->\n<li>{$pill}</li>\n<!-- /wp:list-item -->\n\n";
}

$ggtHero = <<<HTML
<!-- wp:group {"tagName":"section","align":"full","anchor":"top","className":"ggt-sec ggt-sec--hero ggt-hero-v5","style":{"spacing":{"margin":{"top":"0"},"padding":{"top":"0","bottom":"0"},"blockGap":"0"}},"layout":{"type":"constrained"},"metadata":{"name":"Hero"}} -->
<section class="wp-block-group alignfull ggt-sec ggt-sec--hero ggt-hero-v5" id="top" style="margin-top:0;padding-top:0;padding-bottom:0"><!-- wp:group {"align":"wide","className":"ggt-hero-v5__wrap","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide ggt-hero-v5__wrap"><!-- wp:columns {"className":"ggt-hero-v5__grid"} -->
<div class="wp-block-columns ggt-hero-v5__grid"><!-- wp:column {"width":"45%","className":"ggt-hero-v5__intro","style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-column ggt-hero-v5__intro" style="flex-basis:45%"><!-- wp:paragraph {"className":"ggt-hero-v5__badge"} -->
<p class="ggt-hero-v5__badge">Minnesota's #1 Cannabis Transport Company</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"ggt-hero-v5__pills"} -->
<ul class="wp-block-list ggt-hero-v5__pills">{$ggtPills}</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":1,"className":"ggt-hero-v5__title"} -->
<h1 class="wp-block-heading ggt-hero-v5__title">Moving Cannabis <mark style="background-color:rgba(0,0,0,0)" class="has-inline-color has-green-500-color">Forward.</mark></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"ggt-hero-v5__lead"} -->
<p class="ggt-hero-v5__lead">We're NOT a franchise or big business. We're a woman-owned, minority-led family business built in Minnesota, for Minnesota. We offer same-day, statewide, compliance-first B2B cannabis logistics for cultivators, manufacturers, labs, and dispensaries.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"ggt-hero-v5__rating"} -->
<p class="ggt-hero-v5__rating"><span class="ggt-hero-v5__stars" aria-hidden="true">★★★★★</span> <strong>5.0</strong> · Five-star Google reviews</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"ggt-reviews","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"},"metadata":{"name":"Google reviews"}} -->
<div class="wp-block-group ggt-reviews">{$ggtReviewMarkup}</div>
<!-- /wp:group -->

<!-- wp:buttons {"className":"ggt-hero-v5__ctas","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-buttons ggt-hero-v5__ctas"><!-- wp:button {"className":"is-style-ggt-split ggt-split-dark"} -->
<div class="wp-block-button is-style-ggt-split ggt-split-dark"><a class="wp-block-button__link wp-element-button" href="{$ggtQuoteUrl}">Get a Free Quote</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-ggt-text"} -->
<div class="wp-block-button is-style-ggt-text"><a class="wp-block-button__link wp-element-button" href="#services">Our Services</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"55%","style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-column" style="flex-basis:55%"><!-- wp:group {"className":"ggt-hero-form-slot","style":{"spacing":{"blockGap":"0"},"shadow":"var:preset|shadow|card"},"layout":{"type":"default"}} -->
<div class="wp-block-group ggt-hero-form-slot" style="box-shadow:var(--wp--preset--shadow--card)"><!-- wp:paragraph -->
<p><a href="{$ggtQuoteUrl}">Get a free quote</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
HTML;

$ggtNew = array_values(array_filter(parse_blocks($ggtHero), static fn ($b) => !empty($b['blockName'])));

if (count($ggtNew) !== 1) {
	WP_CLI::error('Hero markup did not parse to one block.');
}

$blocks[$heroIndex] = $ggtNew[0];

$content = serialize_blocks($blocks);

if ($ggtDryRun) {
	WP_CLI::log(serialize_block($ggtNew[0]));
	WP_CLI::success('Dry run: nothing saved.');
	return;
}

$result = wp_update_post(wp_slash(['ID' => $ggtFront, 'post_content' => $content]), true);

if (is_wp_error($result)) {
	WP_CLI::error($result->get_error_message());
}

WP_CLI::success(sprintf('Homepage (%d): hero is v5 (revision kept).', $ggtFront));

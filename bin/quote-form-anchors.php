<?php

/**
 * Get a Quote page: "Get a Quote Now" buttons further down the page, back up to the form.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/quote-form-anchors.php [dry-run]
 *
 * - The quote form section (`.ggt-sec--quote-form`) gets the anchor `quote-form` (scroll-margin for the sticky nav
 *   comes from the theme's [id] rule).
 * - What Happens Next (`.ggt-sec--quote-next`): a "Get a Quote Now" primary button (ggt-split) under the steps.
 * - Reviews (`.ggt-sec--testimonials`): the same button beside the "5.0 · Five-star Google reviews" line (the line
 *   and the button share a flex row, `.ggt-rating-row`); a button left under the carousel by an earlier run is removed.
 * - FAQ (`.ggt-sec--faq`): label "Before You Book" -> "Still Have Questions?"; the intro's buttons become
 *   "Get a Quote Now" (primary) then Contact Us and View Full FAQ (text links).
 * - Buttons saved earlier as "Back to Quote Form" are relabelled.
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

const GGT_FORM_ANCHOR = 'quote-form';
const GGT_BACK_LABEL = 'Get a Quote Now';
const GGT_OLD_LABELS = ['Back to Quote Form'];

$ggtPage = get_page_by_path('get-a-quote');

if (!$ggtPage) {
	WP_CLI::error('No page with the slug get-a-quote.');
}

$blocks = parse_blocks($ggtPage->post_content);
$done = [];

$ggtParse = static fn (string $markup): array => array_values(array_filter(parse_blocks($markup), static fn ($b) => !empty($b['blockName'])))[0];

// Append a block to a parent's innerBlocks, with a blank line before it like the editor saves.
$ggtAppend = static function (array &$parent, array $child): void {
	$close = array_pop($parent['innerContent']);
	array_push($parent['innerContent'], "\n\n", null, $close);
	$parent['innerBlocks'][] = $child;
};

$ggtSectionIndex = static function (array $blocks, string $class): ?int {
	foreach ($blocks as $i => $block) {
		if (($block['blockName'] ?? '') === 'core/group' && in_array($class, explode(' ', (string) ($block['attrs']['className'] ?? '')), true)) {
			return $i;
		}
	}
	return null;
};

$ggtHasBack = static fn (array $block): bool => strpos(serialize_block($block), 'href="#' . GGT_FORM_ANCHOR . '"') !== false;

$ggtButtons = static fn (): string => '<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|70"}}}} -->' . "\n"
	. '<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--70)"><!-- wp:button {"className":"is-style-ggt-split"} -->' . "\n"
	. '<div class="wp-block-button is-style-ggt-split"><a class="wp-block-button__link wp-element-button" href="#' . GGT_FORM_ANCHOR . '">' . GGT_BACK_LABEL . '</a></div>' . "\n"
	. '<!-- /wp:button --></div>' . "\n"
	. '<!-- /wp:buttons -->';

// --- 1. Anchor on the form section -------------------------------------------------------------------------------
$formIndex = $ggtSectionIndex($blocks, 'ggt-sec--quote-form');

if ($formIndex === null) {
	WP_CLI::error('Get a Quote: no .ggt-sec--quote-form section.');
}

if (strpos($blocks[$formIndex]['innerContent'][0], 'id="' . GGT_FORM_ANCHOR . '"') === false) {
	$blocks[$formIndex]['attrs']['anchor'] = GGT_FORM_ANCHOR;
	$addId = static fn (string $html): string => (string) preg_replace('/^(\s*<section class="[^"]*")(?! id=)/', '$1 id="' . GGT_FORM_ANCHOR . '"', $html, 1);
	$blocks[$formIndex]['innerHTML'] = $addId($blocks[$formIndex]['innerHTML']);
	$blocks[$formIndex]['innerContent'][0] = $addId($blocks[$formIndex]['innerContent'][0]);
	$done[] = 'anchor #' . GGT_FORM_ANCHOR;
}

// --- 2. What Happens Next: button under the steps (the grid's second column) ----------------------------------
$nextIndex = $ggtSectionIndex($blocks, 'ggt-sec--quote-next');

if ($nextIndex === null) {
	WP_CLI::warning('Get a Quote: no What Happens Next section; skipped its link.');
} elseif ($ggtHasBack($blocks[$nextIndex])) {
	WP_CLI::log('Get a Quote: What Happens Next already links back to the form.');
} else {
	// section > wide group > grid group > [sticky column, steps column]
	$grid = &$blocks[$nextIndex]['innerBlocks'][0]['innerBlocks'][0];
	if (($grid['attrs']['layout']['type'] ?? '') !== 'grid' || count($grid['innerBlocks']) < 2) {
		WP_CLI::error('Get a Quote: What Happens Next is not the two-column (Our Team) layout; run bin/quote-next-like-team.php first.');
	}
	$ggtAppend($grid['innerBlocks'][1], $ggtParse($ggtButtons()));
	unset($grid);
	$done[] = 'link under What Happens Next';
}

// --- 3. Reviews: button beside the "5.0 · Five-star Google reviews" line -------------------------------------
$reviewsIndex = $ggtSectionIndex($blocks, 'ggt-sec--testimonials');

// Remove a child block (and the blank line saved before it) from a parent.
$ggtRemoveChild = static function (array &$parent, int $index): void {
	$seen = -1;
	foreach ($parent['innerContent'] as $pos => $part) {
		if ($part === null && ++$seen === $index) {
			$cut = ($pos > 0 && is_string($parent['innerContent'][$pos - 1]) && trim($parent['innerContent'][$pos - 1]) === '') ? $pos - 1 : $pos;
			array_splice($parent['innerContent'], $cut, $pos - $cut + 1);
			break;
		}
	}
	array_splice($parent['innerBlocks'], $index, 1);
};

if ($reviewsIndex === null) {
	WP_CLI::warning('Get a Quote: no reviews section; skipped its link.');
} else {
	$section = &$blocks[$reviewsIndex];

	// Earlier version: the button under the carousel. Take it out.
	foreach ($section['innerBlocks'] as &$group) {
		foreach ($group['innerBlocks'] ?? [] as $k => $child) {
			if (($child['blockName'] ?? '') === 'core/buttons' && $ggtHasBack($child)) {
				$ggtRemoveChild($group, $k);
				$done[] = 'removed the button under the reviews carousel';
				break;
			}
		}
	}
	unset($group);

	// Path (list of innerBlocks indexes, from the section) to the block whose child is the rating paragraph.
	$findRating = static function (array $list, array $path = []) use (&$findRating): ?array {
		foreach ($list as $i => $block) {
			foreach ($block['innerBlocks'] ?? [] as $k => $child) {
				if (($child['blockName'] ?? '') === 'core/paragraph' && strpos((string) ($child['attrs']['className'] ?? ''), 'ggt-rating') !== false) {
					return [[...$path, $i], $k];
				}
			}
			$found = $findRating($block['innerBlocks'] ?? [], [...$path, $i]);
			if ($found !== null) {
				return $found;
			}
		}
		return null;
	};
	$hit = $findRating($section['innerBlocks']);

	if ($hit === null) {
		WP_CLI::warning('Get a Quote: no .ggt-rating line in the reviews; skipped its link.');
	} else {
		[$path, $k] = $hit;
		$parent = &$section;
		foreach ($path as $index) {
			$parent = &$parent['innerBlocks'][$index];
		}

		if (in_array('ggt-rating-row', explode(' ', (string) ($parent['attrs']['className'] ?? '')), true)) {
			WP_CLI::log('Get a Quote: reviews button already beside the rating.');
		} else {
			$rating = $parent['innerBlocks'][$k];

			// The row takes the rating's top margin; the rating itself sits flush in the row.
			unset($rating['attrs']['style']['spacing']['margin']['top']);
			foreach ([['style', 'spacing', 'margin'], ['style', 'spacing'], ['style']] as $keys) {
				$ref = &$rating['attrs'];
				$last = array_pop($keys);
				foreach ($keys as $key) {
					$ref = &$ref[$key];
				}
				if (isset($ref[$last]) && $ref[$last] === []) {
					unset($ref[$last]);
				}
				unset($ref);
			}
			$noMargin = static fn (string $html): string => str_replace(' style="margin-top:var(--wp--preset--spacing--40)"', '', $html);
			$rating['innerHTML'] = $noMargin($rating['innerHTML']);
			$rating['innerContent'] = array_map(static fn ($part) => is_string($part) ? $noMargin($part) : $part, $rating['innerContent']);

			$parent['innerBlocks'][$k] = $ggtParse('<!-- wp:group {"className":"ggt-rating-row","style":{"spacing":{"margin":{"top":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->' . "\n"
				. '<div class="wp-block-group ggt-rating-row" style="margin-top:var(--wp--preset--spacing--40)">' . serialize_block($rating) . "\n\n"
				. '<!-- wp:buttons -->' . "\n"
				. '<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-ggt-split"} -->' . "\n"
				. '<div class="wp-block-button is-style-ggt-split"><a class="wp-block-button__link wp-element-button" href="#' . GGT_FORM_ANCHOR . '">' . GGT_BACK_LABEL . '</a></div>' . "\n"
				. '<!-- /wp:button --></div>' . "\n"
				. '<!-- /wp:buttons --></div>' . "\n"
				. '<!-- /wp:group -->');
			$done[] = 'reviews button beside the 5.0 rating';
		}
		unset($parent);
	}
	unset($hit, $section);
}

// --- 4. FAQ: label and a text link ---------------------------------------------------------------------------
$faqIndex = $ggtSectionIndex($blocks, 'ggt-sec--faq');

if ($faqIndex === null) {
	WP_CLI::warning('Get a Quote: no FAQ section; skipped.');
} else {
	$walk = static function (array &$list) use (&$walk, &$done, $ggtParse, $ggtAppend): void {
		foreach ($list as &$block) {
			$name = $block['blockName'] ?? '';

			if ($name === 'core/paragraph' && strpos($block['innerHTML'], '>Before You Book<') !== false) {
				$block['innerHTML'] = str_replace('>Before You Book<', '>Still Have Questions?<', $block['innerHTML']);
				$block['innerContent'][0] = str_replace('>Before You Book<', '>Still Have Questions?<', $block['innerContent'][0]);
				$done[] = 'FAQ label "Still Have Questions?"';
			}

			// Intro buttons: Get a Quote Now (primary), Contact Us, View Full FAQ (text links).
			if ($name === 'core/buttons' && strpos(serialize_block($block), '>View Full FAQ<') !== false) {
				$first = $block['innerBlocks'][0] ?? [];
				$isPrimaryQuote = strpos((string) ($first['attrs']['className'] ?? ''), 'is-style-ggt-split') !== false
					&& strpos($first['innerHTML'] ?? '', 'href="#' . GGT_FORM_ANCHOR . '"') !== false;

				if (!$isPrimaryQuote) {
					$hrefOf = static function (array $buttons, string $label): ?string {
						foreach ($buttons as $button) {
							if (strpos($button['innerHTML'], '>' . $label . '<') !== false && preg_match('/href="([^"]*)"/', $button['innerHTML'], $m)) {
								return html_entity_decode($m[1]);
							}
						}
						return null;
					};
					$button = static fn (string $style, string $href, string $label): string => '<!-- wp:button {"className":"is-style-ggt-' . $style . '"} -->' . "\n"
						. '<div class="wp-block-button is-style-ggt-' . $style . '"><a class="wp-block-button__link wp-element-button" href="' . esc_attr($href) . '">' . $label . '</a></div>' . "\n"
						. '<!-- /wp:button -->';

					$markup = '<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->' . "\n"
						. '<div class="wp-block-buttons">'
						. $button('split', '#' . GGT_FORM_ANCHOR, GGT_BACK_LABEL) . "\n\n"
						. $button('text', $hrefOf($block['innerBlocks'], 'Contact Us') ?? '#contact', 'Contact Us') . "\n\n"
						. $button('text', $hrefOf($block['innerBlocks'], 'View Full FAQ') ?? '/cannabis-transport-faq/', 'View Full FAQ')
						. '</div>' . "\n"
						. '<!-- /wp:buttons -->';

					$block = $ggtParse($markup);
					$done[] = 'FAQ intro buttons: Get a Quote Now first';
				}
			}

			if (!empty($block['innerBlocks'])) {
				$walk($block['innerBlocks']);
			}
		}
		unset($block);
	};
	$walk($blocks[$faqIndex]['innerBlocks']);
}

// --- 5. Relabel buttons saved with an older label ----------------------------------------------------------------
$relabel = static function (array &$list) use (&$relabel, &$done): void {
	foreach ($list as &$block) {
		if (($block['blockName'] ?? '') === 'core/button' && strpos($block['innerHTML'], 'href="#' . GGT_FORM_ANCHOR . '"') !== false) {
			foreach (GGT_OLD_LABELS as $old) {
				if (strpos($block['innerHTML'], '>' . $old . '<') !== false) {
					$block['innerHTML'] = str_replace('>' . $old . '<', '>' . GGT_BACK_LABEL . '<', $block['innerHTML']);
					$block['innerContent'] = array_map(static fn ($part) => is_string($part) ? str_replace('>' . $old . '<', '>' . GGT_BACK_LABEL . '<', $part) : $part, $block['innerContent']);
					$done[] = 'relabelled "' . $old . '"';
				}
			}
		}
		if (!empty($block['innerBlocks'])) {
			$relabel($block['innerBlocks']);
		}
	}
	unset($block);
};
$relabel($blocks);

if (!$done) {
	WP_CLI::log('Get a Quote: form links already in place; skipped.');
	return;
}

$content = serialize_blocks($blocks);

if (serialize_blocks(parse_blocks($content)) !== $content) {
	WP_CLI::error('Get a Quote: new content does not round-trip through the block parser; nothing changed.');
}

if ($ggtDryRun) {
	WP_CLI::log(sprintf('[dry-run] Get a Quote (%d): %s.', $ggtPage->ID, implode('; ', $done)));
	return;
}

$result = wp_update_post(wp_slash(['ID' => $ggtPage->ID, 'post_content' => $content]), true);

if (is_wp_error($result)) {
	WP_CLI::error($result->get_error_message());
}

WP_CLI::success(sprintf('Get a Quote (%d): %s (revision kept).', $ggtPage->ID, implode('; ', $done)));

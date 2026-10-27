<?php

/**
 * Bring the location pages' copy back to the live site's wording (SEO: same words and headings).
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/location-copy-sync.php [city=<slug>] [dry-run]
 *
 * The redesign kept the live copy but retitled headings (title case, "Services We Provide Around"
 * instead of "Cannabis transport services we provide around"), turned some headings into eyebrow
 * labels or paragraphs, and gave the four bespoke cities (Minneapolis, Red Wing, St. Paul,
 * Stillwater) the shared Compliance and Process sections in place of their own. This restores:
 *
 * - every live heading's wording (sentence case) and level; the highlighted part keeps its colour;
 * - process steps and bespoke "right fit" items as H3s (they were paragraphs);
 * - the bespoke cities' own sections and order: who we support, local coverage, right fit, our
 *   simple process, helpful links, FAQ, regulatory resources, ready to schedule your next run;
 * - small text drift: "visibility—so", St. Cloud's "Two-person transport teams", a space between
 *   numbered-list numbers and their text (crawlers read "01Inventory" otherwise), and the hero
 *   route badge's zone (staging still had the weekday).
 *
 * Design-only additions stay (route/price badges, "Why Choose Us" label, step numbers, buttons).
 * The shared synced patterns "Location: Compliance and checklist" and "Location: Process" are
 * edited once. Idempotent: every change is "set to" or guarded.
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtOpts = [];
foreach ($args ?? [] as $ggtArg) {
	[$ggtKey, $ggtVal] = array_pad(explode('=', $ggtArg, 2), 2, true);
	$ggtOpts[$ggtKey] = $ggtVal;
}

const GGT_BESPOKE = ['minneapolis', 'red-wing', 'st-paul', 'stillwater'];

/* ---------------------------------------------------------------- block helpers */

/**
 * Visit every block depth-first; the callback gets the block by reference and its parent.
 */
function ggt_walk(array &$blocks, callable $fn, ?array &$parent = null): void
{
	foreach ($blocks as &$block) {
		if (!empty($block['blockName'])) {
			$fn($block, $parent);
		}
		if (!empty($block['innerBlocks'])) {
			ggt_walk($block['innerBlocks'], $fn, $block);
		}
	}
}

/**
 * First block matching a predicate (by reference), or null.
 */
function &ggt_find(array &$blocks, callable $match)
{
	$null = null;
	foreach ($blocks as &$block) {
		if (!empty($block['blockName']) && $match($block)) {
			return $block;
		}
		if (!empty($block['innerBlocks'])) {
			$found = &ggt_find($block['innerBlocks'], $match);
			if ($found !== null) {
				return $found;
			}
		}
	}
	return $null;
}

function ggt_text(array $block): string
{
	return trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(serialize_block($block))));
}

function ggt_class(array $block): string
{
	return (string) ($block['attrs']['className'] ?? '');
}

/**
 * Replace a leaf block's inner HTML (between its outer tag's open and close).
 */
function ggt_set_inner(array &$block, string $html): void
{
	$block['innerHTML'] = preg_replace('#^(\s*<[a-z0-9]+[^>]*>).*(</[a-z0-9]+>\s*)$#s', '${1}' . str_replace(['\\', '$'], ['\\\\', '\\$'], $html) . '${2}', $block['innerHTML']);
	$block['innerContent'] = [$block['innerHTML']];
}

/**
 * Heading text with [[...]] wrapped in the heading's existing highlight <mark>, if it has one.
 */
function ggt_set_heading(array &$block, string $text): void
{
	$mark = preg_match('#<mark\b[^>]*>#', $block['innerHTML'], $m) ? $m[0] : '';
	$html = preg_replace_callback('#\[\[(.*?)\]\]#', static fn ($t) => $mark ? $mark . $t[1] . '</mark>' : $t[1], esc_html_ggt($text));
	ggt_set_inner($block, $html);
}

function esc_html_ggt(string $text): string
{
	return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
}

/**
 * A paragraph block as a heading of the given level, keeping its classes, styles and text.
 */
function ggt_to_heading(array $p, int $level, ?string $text = null): array
{
	$attrs = $p['attrs'];
	unset($attrs['dropCap'], $attrs['direction']);
	$attrs = ['level' => $level] + $attrs;
	$html = preg_replace('#^(\s*)<p\b#', '$1<h' . $level, $p['innerHTML']);
	$html = preg_replace('#</p>(\s*)$#', '</h' . $level . '>$1', $html);
	$html = preg_match('#^\s*<h\d class="#', $html)
		? preg_replace('#^(\s*<h\d class=")#', '$1wp-block-heading ', $html)
		: preg_replace('#^(\s*<h\d)#', '$1 class="wp-block-heading"', $html);
	$heading = ['blockName' => 'core/heading', 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => $html, 'innerContent' => [$html]];
	if ($text !== null) {
		ggt_set_inner($heading, esc_html_ggt($text));
	}
	return $heading;
}

/**
 * Remove the n-th inner block of a parent (and its innerContent placeholder).
 */
function ggt_remove_child(array &$parent, int $index): void
{
	$seen = -1;
	foreach ($parent['innerContent'] as $i => $chunk) {
		if ($chunk === null && ++$seen === $index) {
			array_splice($parent['innerContent'], $i, 1);
			break;
		}
	}
	array_splice($parent['innerBlocks'], $index, 1);
}

/**
 * Remove every inner block of $parent that matches.
 */
function ggt_remove_children(array &$parent, callable $match): int
{
	$removed = 0;
	for ($i = count($parent['innerBlocks']) - 1; $i >= 0; $i--) {
		if ($match($parent['innerBlocks'][$i])) {
			ggt_remove_child($parent, $i);
			$removed++;
		}
	}
	return $removed;
}

/**
 * Remove matching blocks anywhere below a block.
 */
function ggt_remove_deep(array &$block, callable $match): int
{
	$removed = ggt_remove_children($block, $match);
	foreach ($block['innerBlocks'] as &$child) {
		$removed += ggt_remove_deep($child, $match);
	}
	return $removed;
}

function ggt_is_label(array $b): bool
{
	return $b['blockName'] === 'core/paragraph' && str_contains(ggt_class($b), 'is-style-ggt-label');
}

function ggt_section_class(array $b): string
{
	return $b['blockName'] === 'core/group' ? ggt_class($b) : '';
}

/**
 * Top-level section of a post by its ggt-sec--<name> class (by reference).
 */
function &ggt_section(array &$blocks, string $name)
{
	return ggt_find($blocks, static fn ($b) => preg_match('/\bggt-sec--' . preg_quote($name, '/') . '\b/', ggt_section_class($b)));
}

function &ggt_heading_in(array &$blocks, int $level = 2)
{
	return ggt_find($blocks, static fn ($b) => $b['blockName'] === 'core/heading' && (int) ($b['attrs']['level'] ?? 2) === $level);
}

/* ---------------------------------------------------------------- shared edits */

/**
 * Process section (pattern "Location: Process" or a bespoke copy of it).
 *
 * @param array<string, mixed> $copy Bespoke wording, or [] for the shared pattern.
 */
function ggt_process(array &$section, array $copy = []): void
{
	ggt_remove_deep($section, 'ggt_is_label');

	$h2 = &ggt_heading_in($section['innerBlocks'], 2);
	ggt_set_heading($h2, $copy['heading'] ?? 'How our [[process works]]');

	$intro = &ggt_find($section['innerBlocks'], static fn ($b) => $b['blockName'] === 'core/paragraph' && str_contains($b['innerHTML'], 'has-lead-font-size'));
	ggt_set_inner($intro, esc_html_ggt($copy['intro'] ?? "Keeping transport simple is part of staying compliant. Here's what to expect:"));

	// Card in the left column: "Updates and education" (shared) or "Helpful links for planning" (bespoke).
	$card = &ggt_find($section['innerBlocks'], static fn ($b) => $b['blockName'] === 'core/group' && ($b['attrs']['backgroundColor'] ?? '') === 'white');
	$cardHeading = &ggt_find($card['innerBlocks'], static fn ($b) => $b['blockName'] === 'core/heading');
	if ($copy) {
		$cardHeading['attrs']['level'] = 2;
		$cardHeading['innerHTML'] = preg_replace(['#<h3\b#', '#</h3>#'], ['<h2', '</h2>'], $cardHeading['innerHTML']);
		$cardHeading['innerContent'] = [$cardHeading['innerHTML']];
		ggt_set_heading($cardHeading, 'Helpful links for planning');
		$cardText = &ggt_find($card['innerBlocks'], static fn ($b) => $b['blockName'] === 'core/paragraph');
		ggt_set_inner($cardText, 'Get started: <a href="/get-a-quote/">Get a Quote</a>');
		ggt_remove_children($card, static fn ($b) => $b['blockName'] === 'core/group');
	} else {
		ggt_set_heading($cardHeading, 'Updates and education');
	}

	// Steps: title (pill) as H3, description.
	$steps = &ggt_find($section['innerBlocks'], static fn ($b) => str_contains(ggt_class($b), 'ggt-steps'));
	$n = 0;
	foreach ($steps['innerBlocks'] as &$step) {
		if ($step['blockName'] !== 'core/columns') {
			continue;
		}
		ggt_walk($step['innerBlocks'], static function (&$b) use ($copy, $n) {
			if (str_contains(ggt_class($b), 'ggt-pill') && in_array($b['blockName'], ['core/paragraph', 'core/heading'], true)) {
				$title = $copy['steps'][$n][0] ?? null;
				$b = $b['blockName'] === 'core/paragraph' ? ggt_to_heading($b, 3, $title) : $b;
				if ($title !== null) {
					ggt_set_inner($b, esc_html_ggt($title));
				}
			}
			if ($b['blockName'] === 'core/paragraph' && str_contains($b['innerHTML'], 'has-body-md-font-size') && isset($copy['steps'][$n][1])) {
				ggt_set_inner($b, esc_html_ggt($copy['steps'][$n][1]));
			}
		});
		$n++;
	}
}

/**
 * Pattern "Location: Compliance and checklist".
 */
function ggt_compliance(array &$section): void
{
	ggt_walk($section['innerBlocks'], static function (&$b) {
		if ($b['blockName'] !== 'core/heading') {
			return;
		}
		$text = ggt_text($b);
		if (stripos($text, 'Metrc') !== false) {
			ggt_set_heading($b, 'Metrc-ready compliance for cannabis transport');
		} elseif (stripos($text, 'checklist') !== false) {
			ggt_set_heading($b, 'Quick checklist for a smooth pickup');
		}
	});
}

/**
 * Bespoke "Regulatory and compliance resources": the compliance card alone, full width.
 */
function ggt_regulatory(array $section): array
{
	$section['attrs']['metadata'] = ['name' => 'Regulatory resources'];
	$section['attrs']['className'] = 'ggt-sec ggt-sec--regulatory';
	$section['innerHTML'] = str_replace('ggt-sec--compliance', 'ggt-sec--regulatory', $section['innerHTML']);
	foreach ($section['innerContent'] as &$chunk) {
		if (is_string($chunk)) {
			$chunk = str_replace('ggt-sec--compliance', 'ggt-sec--regulatory', $chunk);
		}
	}
	unset($chunk);

	$grid = &$section['innerBlocks'][0];
	$grid['attrs']['layout']['columnCount'] = 1;
	ggt_remove_child($grid, 1); // the checklist card

	$card = &$grid['innerBlocks'][0];
	$h2 = &ggt_heading_in($card['innerBlocks'], 2);
	ggt_set_heading($h2, 'Regulatory and compliance resources');
	$p = &ggt_find($card['innerBlocks'], static fn ($b) => $b['blockName'] === 'core/paragraph');
	ggt_set_inner($p, 'These references can help operators align transport procedures with Minnesota requirements and common track-and-trace practices:');

	$links = [
		['https://www.revisor.mn.gov/statutes/cite/342.36', 'Minnesota Statutes 342.36 (shipping manifest requirement)'],
		['https://www.revisor.mn.gov/rules/9810.2300/', 'Minnesota Rules 9810.2300 (shipping manifest availability and transport requirements)'],
		['https://mn.gov/ocm/businesses/licensing/license-types.jsp', 'MN Office of Cannabis Management – License types'],
		['https://mn.gov/ocm/media/guidance-memos/index.jsp', 'MN Office of Cannabis Management – Guidance memos'],
		['https://www.metrc.com/partner/minnesota/', 'Metrc Minnesota – training resources'],
	];
	$buttons = &ggt_find($card['innerBlocks'], static fn ($b) => $b['blockName'] === 'core/buttons');
	$template = $buttons['innerBlocks'][0];
	$open = (string) $buttons['innerContent'][0];
	$close = (string) end($buttons['innerContent']);
	$buttons['innerBlocks'] = [];
	$buttons['innerContent'] = [$open];
	foreach ($links as $i => [$href, $label]) {
		$button = $template;
		$button['innerHTML'] = preg_replace('#<a ([^>]*)href="[^"]*"([^>]*)>.*?</a>#s', '<a $1href="' . esc_url($href) . '"$2>' . esc_html_ggt($label) . '↗</a>', $button['innerHTML']);
		$button['innerContent'] = [$button['innerHTML']];
		$buttons['innerBlocks'][] = $button;
		$buttons['innerContent'][] = $i ? "\n\n" : '';
		$buttons['innerContent'][] = null;
	}
	$buttons['innerContent'][] = $close;
	$buttons['innerHTML'] = $open . $close;

	return $section;
}

/* ---------------------------------------------------------------- patterns */

function ggt_save(int $id, string $old, array $blocks, string $label, array $opts): void
{
	$new = serialize_blocks($blocks);
	if ($new === $old) {
		WP_CLI::log("{$label}\tunchanged");
		return;
	}
	if (isset($opts['dry-run'])) {
		$a = wp_tempnam('a');
		$b = wp_tempnam('b');
		file_put_contents($a, $old);
		file_put_contents($b, $new);
		WP_CLI::log("{$label}\t(dry run)\n" . shell_exec('diff -u ' . escapeshellarg($a) . ' ' . escapeshellarg($b) . " --label {$label}-before --label {$label}-after"));
		unlink($a);
		unlink($b);
		return;
	}
	$saved = wp_update_post(['ID' => $id, 'post_content' => wp_slash($new)], true);
	WP_CLI::log("{$label}\t" . (is_wp_error($saved) ? 'ERROR ' . $saved->get_error_message() : 'updated'));
}

$ggtPattern = static function (string $title): ?WP_Post {
	$posts = get_posts(['post_type' => 'wp_block', 'title' => $title, 'post_status' => 'publish', 'numberposts' => 1]);
	return $posts[0] ?? null;
};

$ggtCompliancePattern = $ggtPattern('Location: Compliance and checklist');
$ggtProcessPattern = $ggtPattern('Location: Process');

if (!$ggtCompliancePattern || !$ggtProcessPattern) {
	WP_CLI::error('Synced patterns "Location: Compliance and checklist" / "Location: Process" not found.');
}

// Bespoke sections are built from the patterns as they are before this run's edits.
$ggtComplianceSource = parse_blocks($ggtCompliancePattern->post_content)[0];
$ggtProcessSource = parse_blocks($ggtProcessPattern->post_content)[0];

if (!isset($ggtOpts['city'])) {
	$blocks = parse_blocks($ggtCompliancePattern->post_content);
	ggt_compliance($blocks[0]);
	ggt_save($ggtCompliancePattern->ID, $ggtCompliancePattern->post_content, $blocks, 'pattern-compliance', $ggtOpts);

	$blocks = parse_blocks($ggtProcessPattern->post_content);
	ggt_process($blocks[0]);
	ggt_save($ggtProcessPattern->ID, $ggtProcessPattern->post_content, $blocks, 'pattern-process', $ggtOpts);
}

/* ---------------------------------------------------------------- locations */

$ggtBespokeProcess = [
	'heading' => 'Our simple [[process]]',
	'intro' => 'We keep scheduling and execution straightforward:',
	'steps' => [
		['Step 1 – Request a quote', 'Send pickup and drop-off details, preferred windows, and facility info. We follow up with the key questions needed to schedule correctly.'],
		['Step 2 – Schedule and confirm', 'Once details are confirmed, we coordinate timing and send a confirmation so both sender and receiver know what to expect.'],
		['Step 3 – Pickup and drop-off', 'On transport day, we communicate before arrival and complete the handoff with documentation aligned to your compliance needs.'],
	],
];

foreach (get_posts(['post_type' => 'location', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'name', 'order' => 'ASC']) as $ggtPost) {
	$slug = $ggtPost->post_name;
	if (isset($ggtOpts['city']) && $ggtOpts['city'] !== $slug) {
		continue;
	}

	$city = $ggtPost->post_title;
	$bespoke = in_array($slug, GGT_BESPOKE, true);
	$blocks = parse_blocks($ggtPost->post_content);

	// Hero route badge: zone, not weekday.
	// Straight from the term tables: WP Engine's WP-CLI runs without the theme's taxonomies registered.
	global $wpdb;
	$regionId = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = 'region' LIMIT 1",
		$ggtPost->ID
	));
	$zone = $regionId ? (string) get_term_meta($regionId, 'ggt_zone', true) : '';
	ggt_walk($blocks, static function (&$b) use ($zone) {
		if ($zone && $b['blockName'] === 'core/button' && preg_match('/Regional Route · (Mon|Tue|Wed|Thu|Fri)\b/', $b['innerHTML'])) {
			$b['innerHTML'] = preg_replace('/(Regional Route · )(Mon|Tue|Wed|Thu|Fri)\b/', '${1}' . $zone, $b['innerHTML']);
			$b['innerContent'] = [$b['innerHTML']];
		}
		// Numbered lists: a space between the number and the text.
		if ($b['blockName'] === 'core/list-item' && str_contains($b['innerHTML'], '<span class="ggt-num">')) {
			$b['innerHTML'] = preg_replace('#(<span class="ggt-num">\d+</span>)(<span)#', '$1 $2', $b['innerHTML']);
			$b['innerContent'] = [$b['innerHTML']];
		}
	});

	// Service area + services (one two-column group).
	$area = &ggt_section($blocks, 'service-area');
	$grid = &$area['innerBlocks'][0];
	$areaIndex = null;
	$servicesIndex = null;
	foreach ($grid['innerBlocks'] as $i => $col) {
		$heading = ggt_text(ggt_find($col['innerBlocks'], static fn ($b) => $b['blockName'] === 'core/heading') ?? ['blockName' => 'x', 'innerHTML' => '', 'innerBlocks' => [], 'attrs' => [], 'innerContent' => ['']]);
		if (preg_match('/service area|local coverage/i', $heading)) {
			$areaIndex = $i;
		} elseif (preg_match('/provide|who we support/i', $heading)) {
			$servicesIndex = $i;
		}
	}

	$areaHeading = &ggt_find($grid['innerBlocks'][$areaIndex]['innerBlocks'], static fn ($b) => $b['blockName'] === 'core/heading');
	$servicesHeading = &ggt_find($grid['innerBlocks'][$servicesIndex]['innerBlocks'], static fn ($b) => $b['blockName'] === 'core/heading');

	if ($bespoke) {
		ggt_set_heading($areaHeading, "Local coverage: [[{$city}]] plus surrounding cities");
		ggt_set_heading($servicesHeading, "Who we support in and around [[{$city}]]");

		// "Not listed?" after the city pills, as on the live page.
		$col = &$grid['innerBlocks'][$areaIndex];
		$notListed = null;
		foreach ($col['innerBlocks'] as $i => $child) {
			if ($child['blockName'] === 'core/paragraph' && str_contains($child['innerHTML'], 'Not listed?')) {
				$notListed = $i;
			}
		}
		$last = count($col['innerBlocks']) - 1;
		if ($notListed !== null && $notListed < $last) {
			$moved = $col['innerBlocks'][$notListed];
			ggt_remove_child($col, $notListed);
			$col['innerBlocks'][] = $moved;
			$closing = array_pop($col['innerContent']);
			$col['innerContent'][] = "\n\n";
			$col['innerContent'][] = null;
			$col['innerContent'][] = $closing;
		}
		// Below the pills it needs space above rather than below.
		ggt_walk($col['innerBlocks'], static function (&$b) {
			if ($b['blockName'] === 'core/paragraph' && str_contains($b['innerHTML'], 'Not listed?') && isset($b['attrs']['style']['spacing']['margin']['bottom'])) {
				$b['attrs']['style']['spacing']['margin'] = ['top' => 'var:preset|spacing|50'];
				$b['innerHTML'] = str_replace('margin-bottom:var(--wp--preset--spacing--60)', 'margin-top:var(--wp--preset--spacing--50)', $b['innerHTML']);
				$b['innerContent'] = [$b['innerHTML']];
			}
		});
		unset($col);

		// Who we support comes first.
		if ($servicesIndex > $areaIndex) {
			[$grid['innerBlocks'][$areaIndex], $grid['innerBlocks'][$servicesIndex]] = [$grid['innerBlocks'][$servicesIndex], $grid['innerBlocks'][$areaIndex]];
		}
	} else {
		ggt_set_heading($areaHeading, "Service area around [[{$city}]]");
		$servicesTail = $slug === 'albert-lea' ? $city : "{$city} MN";
		ggt_set_heading($servicesHeading, "Cannabis transport services we provide around [[{$servicesTail}]]");
	}
	unset($areaHeading, $servicesHeading, $grid, $area);

	// Why GGT.
	$why = &ggt_section($blocks, 'why-ggt');
	$whyHeading = &ggt_heading_in($why['innerBlocks'], 2);
	if ($bespoke) {
		ggt_set_heading($whyHeading, 'What makes [[Going Green Transport LLC]] the right fit');
		ggt_remove_deep($why, static fn ($b) => $b['blockName'] === 'core/group' && count($b['innerBlocks']) === 1 && str_contains($b['innerBlocks'][0]['innerHTML'], 'the place for shortcuts'));
		ggt_walk($why['innerBlocks'], static function (&$b) {
			if ($b['blockName'] === 'core/paragraph' && str_contains($b['innerHTML'], 'has-ui-lg-font-size')) {
				$b = ggt_to_heading($b, 3);
			}
			// Item rows came over as number | title | description, with the description squeezed
			// into a 64px column: put it under the title.
			if ($b['blockName'] === 'core/columns' && count($b['innerBlocks']) === 3 && ($b['innerBlocks'][2]['attrs']['width'] ?? '') === '64px') {
				$text = $b['innerBlocks'][2]['innerBlocks'][0];
				ggt_remove_child($b, 2);
				$closing = array_pop($b['innerContent']);
				while ($b['innerContent'] && is_string(end($b['innerContent'])) && trim(end($b['innerContent'])) === '') {
					array_pop($b['innerContent']);
				}
				$b['innerContent'][] = $closing;
				$title = &$b['innerBlocks'][1];
				$title['innerBlocks'][] = $text;
				$titleClosing = array_pop($title['innerContent']);
				$title['innerContent'][] = "\n\n";
				$title['innerContent'][] = null;
				$title['innerContent'][] = $titleClosing;
				unset($title);
			}
		});
	} else {
		ggt_set_heading($whyHeading, 'Why Minnesota businesses choose [[Going Green Transport, LLC]]');
		ggt_walk($why['innerBlocks'], static function (&$b) use ($slug) {
			if ($b['blockName'] === 'core/paragraph' && str_contains($b['innerHTML'], 'visibility, so your team')) {
				$b['innerHTML'] = str_replace('visibility, so your team', 'visibility—so your team', $b['innerHTML']);
				$b['innerContent'] = [$b['innerHTML']];
			}
			if ($slug === 'st-cloud' && $b['blockName'] === 'core/paragraph' && str_contains($b['innerHTML'], '>Our transport teams and locked cargo')) {
				$b['innerHTML'] = str_replace('>Our transport teams and locked cargo', '>Two-person transport teams and locked cargo', $b['innerHTML']);
				$b['innerContent'] = [$b['innerHTML']];
			}
		});
	}
	unset($whyHeading, $why);

	// FAQ.
	$faq = &ggt_section($blocks, 'faq');
	ggt_remove_deep($faq, 'ggt_is_label');
	$faqHeading = &ggt_heading_in($faq['innerBlocks'], 2);
	ggt_set_heading($faqHeading, $bespoke ? "FAQ for [[{$city}]] operators" : "FAQ: Cannabis logistics near [[{$city}]]");
	unset($faqHeading, $faq);

	// CTA.
	$cta = &ggt_section($blocks, 'cta');
	$ctaHeading = &ggt_heading_in($cta['innerBlocks'], 2);
	if ($bespoke) {
		ggt_set_heading($ctaHeading, 'Ready to schedule your [[next run?]]');
		$ctaText = &ggt_find($cta['innerBlocks'], static fn ($b) => $b['blockName'] === 'core/paragraph' && str_contains($b['innerHTML'], 'has-lead-lg-font-size'));
		ggt_set_inner($ctaText, 'If you need a compliant transport partner, <a href="/get-a-quote/">Get a Quote</a> and we will follow up with the details to schedule your route.');
		// The link sits on the dark green panel: lime, like the heading highlight.
		if (empty($ctaText['attrs']['style']['elements']['link'])) {
			$ctaText['attrs']['style']['elements']['link']['color']['text'] = 'var:preset|color|lime-500';
			$ctaText['innerHTML'] = preg_replace('#^(\s*<p class=")#', '$1has-link-color ', $ctaText['innerHTML']);
			$ctaText['innerContent'] = [$ctaText['innerHTML']];
		}
		unset($ctaText);
	} else {
		ggt_set_heading($ctaHeading, 'Ready to [[schedule?]]');
	}
	unset($ctaHeading, $cta);

	// Bespoke: own process + regulatory sections instead of the shared patterns.
	if ($bespoke) {
		$refs = [];
		foreach ($blocks as $i => $b) {
			if ($b['blockName'] === 'core/block' && in_array((int) ($b['attrs']['ref'] ?? 0), [$ggtCompliancePattern->ID, $ggtProcessPattern->ID], true)) {
				$refs[] = $i;
			}
		}
		if ($refs) {
			$process = $ggtProcessSource;
			$process['attrs']['metadata'] = ['name' => 'Process'];
			ggt_process($process, $ggtBespokeProcess);
			$regulatory = ggt_regulatory($ggtComplianceSource);

			// Process where the patterns were.
			$first = $refs[0];
			foreach (array_reverse($refs) as $i) {
				array_splice($blocks, $i, 1);
			}
			array_splice($blocks, $first, 0, [$process]);

			// Regulatory after the FAQ.
			foreach ($blocks as $i => $b) {
				if (preg_match('/\bggt-sec--faq\b/', ggt_section_class($b))) {
					array_splice($blocks, $i + 1, 0, [['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => "\n\n", 'innerContent' => ["\n\n"]], $regulatory]);
					break;
				}
			}
		}
	}

	ggt_save($ggtPost->ID, $ggtPost->post_content, $blocks, $slug, $ggtOpts);
}

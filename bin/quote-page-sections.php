<?php

/**
 * Get a Quote page: add "What Drives Us" and the operator reviews, and the #contact anchor on Prefer to Talk.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/quote-page-sections.php [dry-run]
 *
 * Copied from the same site's own pages (so attachment IDs and upload URLs are right on every environment):
 * - About Us `.ggt-sec--principles` ("What Drives Us / Six Principles. Every Single Run.") and
 * - the homepage `.ggt-sec--testimonials` ("What Our Minnesota Licensed Cannabis Operators Say.", the reviews
 *   carousel) go in before the FAQ, in that order.
 * - The "Prefer to Talk?" section (`.ggt-sec--cta`) gets the anchor `contact`, the target of the FAQ's Contact Us
 *   button (the FAQ intro itself: bin/quote-faq-sticky.php).
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

$ggtHasClass = static fn (array $block, string $class): bool => ($block['blockName'] ?? '') === 'core/group'
	&& in_array($class, explode(' ', (string) ($block['attrs']['className'] ?? '')), true);

// Top-level section of a page by class.
$ggtSection = static function (array $blocks, string $class) use ($ggtHasClass): ?int {
	foreach ($blocks as $i => $block) {
		if ($ggtHasClass($block, $class)) {
			return $i;
		}
	}
	return null;
};

$ggtSeparator = ['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => "\n\n", 'innerContent' => ["\n\n"]];

$ggtQuote = get_page_by_path('get-a-quote');
$ggtAbout = get_page_by_path('about-us');
$ggtHome = get_post((int) get_option('page_on_front'));

if (!$ggtQuote || !$ggtAbout || !$ggtHome) {
	WP_CLI::error('Need the get-a-quote and about-us pages and a static front page.');
}

$blocks = parse_blocks($ggtQuote->post_content);
$aboutBlocks = parse_blocks($ggtAbout->post_content);
$homeBlocks = parse_blocks($ggtHome->post_content);
$done = [];

// --- 1. What Drives Us + reviews, before the FAQ ---------------------------------------------------------------
$faqIndex = $ggtSection($blocks, 'ggt-sec--faq');

if ($faqIndex === null) {
	WP_CLI::error('Get a Quote: no .ggt-sec--faq section.');
}

$insert = [];
foreach ([['ggt-sec--principles', $aboutBlocks, 'What Drives Us (About Us)'], ['ggt-sec--testimonials', $homeBlocks, 'reviews (homepage)']] as [$class, $source, $label]) {
	if ($ggtSection($blocks, $class) !== null) {
		WP_CLI::log("Get a Quote: already has {$label}; skipped.");
		continue;
	}
	$index = $ggtSection($source, $class);
	if ($index === null) {
		WP_CLI::error("Source section .{$class} not found for {$label}; nothing changed.");
	}
	$section = $source[$index];
	unset($section['attrs']['anchor']); // Keep ids unique to their own page.
	$section['innerContent'][0] = preg_replace('/^(\s*<section[^>]*?) id="[^"]*"/', '$1', $section['innerContent'][0]);
	$section['innerHTML'] = preg_replace('/^(\s*<section[^>]*?) id="[^"]*"/', '$1', $section['innerHTML']);
	array_push($insert, $section, $ggtSeparator);
	$done[] = "added {$label}";
}

if ($insert) {
	array_splice($blocks, $faqIndex, 0, $insert);
	$faqIndex += count($insert);
}

// --- 2. Anchor on Prefer to Talk (Contact Us target) -----------------------------------------------------------
$ctaIndex = $ggtSection($blocks, 'ggt-sec--cta');

if ($ctaIndex === null) {
	WP_CLI::warning('Get a Quote: no .ggt-sec--cta section; Contact Us will not scroll anywhere.');
} elseif (($blocks[$ctaIndex]['attrs']['anchor'] ?? '') !== 'contact' || strpos($blocks[$ctaIndex]['innerContent'][0], 'id="contact"') === false) {
	$blocks[$ctaIndex]['attrs']['anchor'] = 'contact';
	// The saved opening tag ("\n<section class=…") carries the id too, or the editor flags the block as changed.
	$blocks[$ctaIndex]['innerHTML'] = preg_replace('/^(\s*<section class="[^"]*")(?! id=)/', '$1 id="contact"', $blocks[$ctaIndex]['innerHTML'], 1);
	$blocks[$ctaIndex]['innerContent'][0] = preg_replace('/^(\s*<section class="[^"]*")(?! id=)/', '$1 id="contact"', $blocks[$ctaIndex]['innerContent'][0], 1);
	$done[] = 'anchor #contact on Prefer to Talk';
}

if (!$done) {
	WP_CLI::log('Get a Quote: nothing to do.');
	return;
}

$content = serialize_blocks($blocks);

if (serialize_blocks(parse_blocks($content)) !== $content) {
	WP_CLI::error('Get a Quote: new content does not round-trip through the block parser; nothing changed.');
}

if ($ggtDryRun) {
	WP_CLI::log(sprintf('[dry-run] Get a Quote (%d): %s (%d -> %d bytes).', $ggtQuote->ID, implode('; ', $done), strlen($ggtQuote->post_content), strlen($content)));
	return;
}

$result = wp_update_post(wp_slash(['ID' => $ggtQuote->ID, 'post_content' => $content]), true);

if (is_wp_error($result)) {
	WP_CLI::error($result->get_error_message());
}

WP_CLI::success(sprintf('Get a Quote (%d): %s (revision kept).', $ggtQuote->ID, implode('; ', $done)));

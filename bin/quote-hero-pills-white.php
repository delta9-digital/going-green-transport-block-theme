<?php

/**
 * Get a Quote page: the banner pills (Licensed, Insured, $0 Onboarding) in white instead of gold.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/quote-hero-pills-white.php [dry-run]
 *
 * In the `.ggt-sec--quote-hero` section, every `.ggt-pill` paragraph gets white text (palette `white`) and a white
 * 1px outline instead of gold-500 / #C9A66B. Block attributes and saved markup change together (valid in the
 * editor). Other pages' pills keep their colours.
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

$ggtPage = get_page_by_path('get-a-quote');

if (!$ggtPage) {
	WP_CLI::error('No page with the slug get-a-quote.');
}

$blocks = parse_blocks($ggtPage->post_content);
$found = 0;
$changed = 0;

$walk = static function (array &$list, bool $inHero) use (&$walk, &$found, &$changed): void {
	foreach ($list as &$block) {
		$classes = explode(' ', (string) ($block['attrs']['className'] ?? ''));
		$hero = $inHero || (($block['blockName'] ?? '') === 'core/group' && in_array('ggt-sec--quote-hero', $classes, true));

		if ($hero && ($block['blockName'] ?? '') === 'core/paragraph' && in_array('ggt-pill', $classes, true)) {
			$found++;
			if (($block['attrs']['textColor'] ?? '') !== 'white') {
				$block['attrs']['textColor'] = 'white';
				$block['attrs']['style']['border']['color'] = '#FFFFFF';

				$fix = static fn (string $html): string => str_replace(
					['has-gold-500-color', 'border-color:#C9A66B'],
					['has-white-color', 'border-color:#FFFFFF'],
					$html
				);
				$block['innerHTML'] = $fix($block['innerHTML']);
				$block['innerContent'] = array_map(static fn ($part) => is_string($part) ? $fix($part) : $part, $block['innerContent']);
				$changed++;
			}
		}

		if (!empty($block['innerBlocks'])) {
			$walk($block['innerBlocks'], $hero);
		}
	}
	unset($block);
};
$walk($blocks, false);

if ($found === 0) {
	WP_CLI::error('Get a Quote: no .ggt-pill paragraphs in the .ggt-sec--quote-hero section.');
}

if ($changed === 0) {
	WP_CLI::log("Get a Quote: banner pills already white ({$found}); skipped.");
	return;
}

$content = serialize_blocks($blocks);

if ($ggtDryRun) {
	WP_CLI::log(sprintf('[dry-run] Get a Quote (%d): would make %d of %d banner pills white.', $ggtPage->ID, $changed, $found));
	return;
}

$result = wp_update_post(wp_slash(['ID' => $ggtPage->ID, 'post_content' => $content]), true);

if (is_wp_error($result)) {
	WP_CLI::error($result->get_error_message());
}

WP_CLI::success(sprintf('Get a Quote (%d): %d banner pills now white (revision kept).', $ggtPage->ID, $changed));

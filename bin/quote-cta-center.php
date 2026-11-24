<?php

/**
 * Get a Quote page: center the "Prefer to Talk?" buttons.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/quote-cta-center.php [dry-run]
 *
 * The heading and text of the `.ggt-sec--cta` card are centered, but its buttons block has no layout, so the Call
 * and Email buttons sit at the left. It gets the layout of the About page's centered CTA buttons (flex, wrap,
 * centered, spacing 40). Only block attributes change (the layout classes are generated at render time).
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

$ggtBlocks = parse_blocks($ggtPage->post_content);
$ggtFound = 0;
$ggtChanged = 0;

// Every core/buttons block inside a .ggt-sec--cta section.
$ggtWalk = static function (array &$blocks, bool $inCta) use (&$ggtWalk, &$ggtFound, &$ggtChanged): void {
	foreach ($blocks as &$block) {
		$isCta = $inCta || (($block['blockName'] ?? '') === 'core/group' && in_array('ggt-sec--cta', explode(' ', (string) ($block['attrs']['className'] ?? '')), true));

		if ($isCta && ($block['blockName'] ?? '') === 'core/buttons') {
			$ggtFound++;
			if (($block['attrs']['layout']['justifyContent'] ?? '') !== 'center') {
				$block['attrs']['layout'] = ['type' => 'flex', 'flexWrap' => 'wrap', 'verticalAlignment' => 'center', 'justifyContent' => 'center'];
				$block['attrs']['style']['spacing']['blockGap'] = 'var:preset|spacing|40';
				$ggtChanged++;
			}
		}

		if (!empty($block['innerBlocks'])) {
			$ggtWalk($block['innerBlocks'], $isCta);
		}
	}
	unset($block);
};
$ggtWalk($ggtBlocks, false);

if ($ggtFound === 0) {
	WP_CLI::error('Get a Quote: no buttons in a .ggt-sec--cta section.');
}

if ($ggtChanged === 0) {
	WP_CLI::log('Get a Quote: Prefer to Talk buttons already centered; skipped.');
	return;
}

$ggtContent = serialize_blocks($ggtBlocks);

if ($ggtDryRun) {
	WP_CLI::log(sprintf('[dry-run] Get a Quote (%d): would center %d buttons block(s).', $ggtPage->ID, $ggtChanged));
	return;
}

$ggtResult = wp_update_post(wp_slash(['ID' => $ggtPage->ID, 'post_content' => $ggtContent]), true);

if (is_wp_error($ggtResult)) {
	WP_CLI::error($ggtResult->get_error_message());
}

WP_CLI::success(sprintf('Get a Quote (%d): Prefer to Talk buttons centered (revision kept).', $ggtPage->ID));

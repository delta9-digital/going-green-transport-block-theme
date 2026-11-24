<?php

/**
 * Quote forms, Your Details (v5 hero order): Website moves up beside Business name, so the rows read
 *   First name | Last name
 *   Business name | Website
 *   Cannabis business type (full width)
 *   Email | Phone
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/forms-quote-website-order.php [dry-run]
 *
 * Saved Forms posts keep their own copy of each block, so the field order lives in each form. In every Going Green
 * form that has both a `website` input and the `operator_type` select, with Website after the select, the Website
 * block moves to just before the select. Only the order changes (names, labels and validation stay), so the tab order
 * matches the 2-column layout in the hero card (_hero.scss). Forms created later from the plugin's quote template
 * (delta9-digital-forms Goinggreen.php getQuoteFields) still need the same order there.
 *
 * Idempotent (skips forms already in this order); saves with wp_update_post (keeps a revision to roll back to).
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);

$ggtIsWebsite = static fn (array $b): bool => ($b['blockName'] ?? '') === 'eightshift-forms/input' && ($b['attrs']['inputInputName'] ?? '') === 'website';
$ggtIsType = static fn (array $b): bool => ($b['blockName'] ?? '') === 'eightshift-forms/select' && ($b['attrs']['selectSelectName'] ?? '') === 'operator_type';

$ggtForms = get_posts(['post_type' => 'eightshift-forms', 'post_status' => 'any', 'numberposts' => -1]);
$ggtChanged = 0;

foreach ($ggtForms as $ggtForm) {
	$blocks = parse_blocks($ggtForm->post_content);
	$moved = false;

	$walk = static function (array &$list) use (&$walk, &$moved, $ggtIsWebsite, $ggtIsType): void {
		foreach ($list as &$block) {
			if (($block['blockName'] ?? '') !== 'eightshift-forms/goinggreen') {
				if (!empty($block['innerBlocks'])) {
					$walk($block['innerBlocks']);
				}
				continue;
			}

			$inner = $block['innerBlocks'] ?? [];
			$web = null;
			$type = null;

			foreach ($inner as $i => $child) {
				if ($ggtIsWebsite($child)) {
					$web = $i;
				}
				if ($ggtIsType($child)) {
					$type = $i;
				}
			}

			if ($web === null || $type === null || $web < $type) {
				continue;
			}

			// innerContent holds one null per inner block in order, so the same count keeps serialisation intact.
			$website = $inner[$web];
			array_splice($inner, $web, 1);
			array_splice($inner, $type, 0, [$website]);
			$block['innerBlocks'] = $inner;
			$moved = true;
		}
	};

	$walk($blocks);

	if (!$moved) {
		WP_CLI::log(sprintf('Form %d "%s": already in order or not a quote form; skipped.', $ggtForm->ID, $ggtForm->post_title));
		continue;
	}

	$ggtChanged++;

	if ($ggtDryRun) {
		WP_CLI::log(sprintf('Form %d "%s": Website would move before Cannabis business type.', $ggtForm->ID, $ggtForm->post_title));
		continue;
	}

	$result = wp_update_post(wp_slash(['ID' => $ggtForm->ID, 'post_content' => serialize_blocks($blocks)]), true);

	if (is_wp_error($result)) {
		WP_CLI::error($result->get_error_message());
	}

	WP_CLI::log(sprintf('Form %d "%s": Website moved before Cannabis business type (revision kept).', $ggtForm->ID, $ggtForm->post_title));
}

WP_CLI::success(sprintf('%d form(s) %s.', $ggtChanged, $ggtDryRun ? 'would change' : 'changed'));

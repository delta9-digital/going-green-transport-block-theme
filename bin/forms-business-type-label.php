<?php

/**
 * Quote forms: the operator-type select is labelled "Cannabis business type" (was "I am a…") and takes several types.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/forms-business-type-label.php [dry-run]
 *
 * Saved Forms posts (post type eightshift-forms) keep their own copy of each field's label; this brings the select
 * operator_type (eightshift-forms/select; attributes selectSelectFieldLabel, selectSelectIsMultiple) in line with the
 * Going Green integration in the forms plugin (Goinggreen.php). Only those attributes change. Idempotent; saves with
 * wp_update_post (keeps a revision to roll back to).
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);
$ggtOld = ['I am a…', 'I am a...', 'I am a'];
$ggtNew = 'Cannabis business type';
$ggtChanged = 0;

foreach (get_posts(['post_type' => 'eightshift-forms', 'post_status' => 'any', 'numberposts' => -1]) as $ggtForm) {
	$blocks = parse_blocks($ggtForm->post_content);
	$count = 0;

	$walk = static function (array &$list) use (&$walk, &$count, $ggtOld, $ggtNew): void {
		foreach ($list as &$block) {
			$isOperatorType = ($block['blockName'] ?? '') === 'eightshift-forms/select'
				&& (($block['attrs']['selectSelectName'] ?? '') === 'operator_type' || in_array($block['attrs']['selectSelectFieldLabel'] ?? '', $ggtOld, true));

			if ($isOperatorType && (($block['attrs']['selectSelectFieldLabel'] ?? '') !== $ggtNew || empty($block['attrs']['selectSelectIsMultiple']))) {
				$block['attrs']['selectSelectFieldLabel'] = $ggtNew;
				// Several licence types per business: multiple, shown as a comma list (theme _forms.scss).
				$block['attrs']['selectSelectIsMultiple'] = true;
				$count++;
			}
			if (!empty($block['innerBlocks'])) {
				$walk($block['innerBlocks']);
			}
		}
		unset($block);
	};
	$walk($blocks);

	if (!$count) {
		continue;
	}

	if ($ggtDryRun) {
		WP_CLI::log(sprintf('[dry-run] Form %d "%s": would relabel %d select(s).', $ggtForm->ID, $ggtForm->post_title, $count));
		continue;
	}

	$result = wp_update_post(wp_slash(['ID' => $ggtForm->ID, 'post_content' => serialize_blocks($blocks)]), true);

	if (is_wp_error($result)) {
		WP_CLI::error("Form {$ggtForm->ID}: " . $result->get_error_message());
	}

	WP_CLI::log(sprintf('Form %d "%s": business type select is "%s", multiple.', $ggtForm->ID, $ggtForm->post_title, $ggtNew));
	$ggtChanged++;
}

WP_CLI::success($ggtDryRun ? 'Dry run done.' : ($ggtChanged ? "{$ggtChanged} form(s) updated (revisions kept)." : 'Nothing to update.'));

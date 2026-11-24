<?php

/**
 * Quote forms: one "Business name or address" input per stop, beside its package stepper.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/forms-stop-fields-combined.php [dry-run]
 *
 * Saved Forms posts (post type eightshift-forms) keep their own copy of each repeater's settings. This brings the
 * pickups / drop-offs repeaters (eightshift-forms/repeater blocks named pickups or dropoffs) in line with the
 * Going Green integration in the forms plugin (delta9-digital-forms, Goinggreen.php):
 * - the old visible business_name sub-field is dropped; business_name, place_address and place_id come back as hidden
 *   sub-fields that a picked Google Places suggestion fills (address: autocomplete places, Minnesota only);
 * - the address sub-field becomes "Business name or address" (still required, still the stop's address in the
 *   payload; business_name is filled later by the OCM lookup);
 * - the packages sub-field is labelled "# of packages";
 * - repeaterFieldsLayout 'row': the input, the package stepper and Done on one line;
 * - repeaterAddAfterComplete: Add pickup / Add drop-off only once the last stop is done (Done, address, packages);
 * - no repeaterRevealAfter: Drop-off #1 shows with the pickup when the stops step opens.
 *
 * Only these block attributes change; the rest of each form is untouched. Idempotent; saves with wp_update_post
 * (keeps a revision to roll back to).
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);

$ggtForms = get_posts(['post_type' => 'eightshift-forms', 'post_status' => 'any', 'numberposts' => -1]);
$ggtChanged = 0;

foreach ($ggtForms as $ggtForm) {
	$blocks = parse_blocks($ggtForm->post_content);
	$changes = [];

	$walk = static function (array &$list) use (&$walk, &$changes): void {
		foreach ($list as &$block) {
			if (($block['blockName'] ?? '') === 'eightshift-forms/repeater' && in_array($block['attrs']['repeaterRepeaterName'] ?? '', ['pickups', 'dropoffs'], true)) {
				$attrs = &$block['attrs'];
				$before = wp_json_encode($attrs);
				$subfields = [];

				foreach ($attrs['repeaterRepeaterSubfields'] ?? [] as $subfield) {
					// Old visible business_name, and the hidden fields: re-added after the address below.
					if (in_array($subfield['name'] ?? '', ['business_name', 'place_address', 'place_id'], true)) {
						continue;
					}
					if (($subfield['name'] ?? '') === 'packages') {
						$subfield['label'] = '# of packages';
					}
					if (($subfield['name'] ?? '') === 'address') {
						$subfield['label'] = 'Business name or address';
						$subfield['placeholder'] = 'Start typing a business or address…';
						$subfield['isRequired'] = true;
						$subfield['summary'] = 'title';
						unset($subfield['summaryOrder']);
						// Google Places suggestions (Minnesota); a pick fills the hidden fields after it.
						$subfield['autocomplete'] = 'places';
						$subfield['autocompleteFill'] = ['name' => 'business_name', 'address' => 'place_address', 'placeId' => 'place_id'];
						$subfield['autocompleteRegion'] = ['country' => 'us', 'state' => 'MN', 'bounds' => [43.499, -97.239, 49.385, -89.483]];
						$subfields[] = $subfield;
						$subfields[] = ['name' => 'business_name', 'type' => 'hidden'];
						$subfields[] = ['name' => 'place_address', 'type' => 'hidden'];
						$subfields[] = ['name' => 'place_id', 'type' => 'hidden'];
						continue;
					}
					$subfields[] = $subfield;
				}

				$attrs['repeaterRepeaterSubfields'] = $subfields;
				$attrs['repeaterRepeaterFieldsLayout'] = 'row';
				// Add only once the last stop is done. Drop-off #1 shows from the start (no repeaterRevealAfter).
				$attrs['repeaterRepeaterAddAfterComplete'] = true;
				unset($attrs['repeaterRepeaterRevealAfter']);

				if (wp_json_encode($attrs) !== $before) {
					$changes[] = $attrs['repeaterRepeaterName'];
				}
				unset($attrs);
			}

			if (!empty($block['innerBlocks'])) {
				$walk($block['innerBlocks']);
			}
		}
		unset($block);
	};
	$walk($blocks);

	if (!$changes) {
		continue;
	}

	$content = serialize_blocks($blocks);

	if ($ggtDryRun) {
		WP_CLI::log(sprintf('[dry-run] Form %d "%s": would update %s.', $ggtForm->ID, $ggtForm->post_title, implode(', ', $changes)));
		continue;
	}

	$result = wp_update_post(wp_slash(['ID' => $ggtForm->ID, 'post_content' => $content]), true);

	if (is_wp_error($result)) {
		WP_CLI::error("Form {$ggtForm->ID}: " . $result->get_error_message());
	}

	WP_CLI::log(sprintf('Form %d "%s": updated %s.', $ggtForm->ID, $ggtForm->post_title, implode(', ', $changes)));
	$ggtChanged++;
}

WP_CLI::success($ggtDryRun ? 'Dry run done.' : ($ggtChanged ? "{$ggtChanged} form(s) updated (revisions kept)." : 'Nothing to update.'));

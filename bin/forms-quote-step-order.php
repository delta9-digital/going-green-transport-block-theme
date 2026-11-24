<?php

/**
 * Quote forms: Your Details first (with the cannabis business type), step 03 "Submit" last (service type, SMS consent,
 * submit button).
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/forms-quote-step-order.php [dry-run]
 *
 * Saved Forms posts keep their own copy of each block. This brings the quote forms in line with the Going Green
 * integration in the forms plugin (delta9-digital-forms, Goinggreen.php getQuoteFields), section order:
 *   01 Your Details (contact fields, cannabis business type right after Business name)
 *   02 Stops (pickups and drop-offs)
 *   03 Submit (service tier, SMS consent) + submit button
 *
 * Multi-step form: the step blocks move with their fields; Your Details gets Continue (no Back), Service Type gets
 * Back (no Continue). Single page form: the data-section-number on each section's first field is renumbered.
 * Anything that is not a known section field stays with the section it sits in; the submit button always ends up last.
 *
 * Section titles: 02 used to be "Pickups & Drop-offs" (now "Stops"), 03 "Service Type" (now "Submit").
 * Also run it on forms already reordered by an earlier version: it moves the business type and the SMS consent.
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

$ggtOrder = ['details', 'stops', 'service'];
$ggtLabels = ['details' => 'Your Details', 'stops' => 'Stops', 'service' => 'Submit'];
$ggtTitles = [
	'Service Type' => 'service',
	'Submit' => 'service',
	'Pickups & Drop-offs' => 'stops',
	'Stops' => 'stops',
	'Your Details' => 'details',
];

/**
 * Section key a block opens, if any: a step block (multi-step) or a field carrying data-section-title (single page).
 */
$ggtOpens = static function (array $block) use ($ggtTitles): ?string {
	$attrs = $block['attrs'] ?? [];

	if (($block['blockName'] ?? '') === 'eightshift-forms/step') {
		return $attrs['stepStepName'] ?? null;
	}

	foreach ($attrs as $key => $value) {
		if (str_ends_with($key, 'FieldAttrs') && is_array($value) && isset($value['data-section-title'])) {
			return $ggtTitles[$value['data-section-title']] ?? null;
		}
	}

	return null;
};

$ggtForms = get_posts(['post_type' => 'eightshift-forms', 'post_status' => 'any', 'numberposts' => -1]);
$ggtChanged = 0;

foreach ($ggtForms as $ggtForm) {
	$blocks = parse_blocks($ggtForm->post_content);
	$note = '';

	$walk = static function (array &$list) use (&$walk, &$note, $ggtOrder, $ggtOpens, $ggtLabels): void {
		foreach ($list as &$block) {
			if (($block['blockName'] ?? '') !== 'eightshift-forms/goinggreen') {
				if (!empty($block['innerBlocks'])) {
					$walk($block['innerBlocks']);
				}
				continue;
			}

			$inner = $block['innerBlocks'] ?? [];
			$groups = array_fill_keys($ggtOrder, []);
			$seen = [];
			$tail = [];
			$current = null;

			foreach ($inner as $child) {
				if (($child['blockName'] ?? '') === 'eightshift-forms/submit') {
					$tail[] = $child;
					continue;
				}

				$opens = $ggtOpens($child);

				if ($opens !== null) {
					if (!isset($groups[$opens])) {
						WP_CLI::warning("Unknown section \"{$opens}\"; form left as is.");
						return;
					}
					$current = $opens;
					$seen[] = $opens;
				}

				if ($current === null) {
					// Not a quote form (leads form, or no section markers).
					continue 2;
				}

				$groups[$current][] = $child;
			}

			if (count(array_unique($seen)) !== count($seen)) {
				WP_CLI::warning('Repeated section marker; form left as is.');
				return;
			}

			if (count($seen) !== 3) {
				continue;
			}

			$notes = [];

			// Cannabis business type: in Your Details, right after Business name (end of the section without one).
			foreach (['stops', 'service'] as $from) {
				foreach ($groups[$from] as $i => $child) {
					if (($child['blockName'] ?? '') !== 'eightshift-forms/select' || ($child['attrs']['selectSelectName'] ?? '') !== 'operator_type') {
						continue;
					}

					array_splice($groups[$from], $i, 1);
					$at = count($groups['details']);

					foreach ($groups['details'] as $j => $detail) {
						if (($detail['attrs']['inputInputName'] ?? '') === 'business_name') {
							$at = $j + 1;
						}
					}

					array_splice($groups['details'], $at, 0, [$child]);
					$notes[] = 'business type -> Your Details';
					break 2;
				}
			}

			// SMS consent: last field of step 03, just above the submit button.
			foreach (['details', 'stops'] as $from) {
				foreach ($groups[$from] as $i => $child) {
					if (($child['attrs']['checkboxesCheckboxesName'] ?? '') !== 'sms_opt_in') {
						continue;
					}

					array_splice($groups[$from], $i, 1);
					$groups['service'][] = $child;
					$notes[] = 'SMS consent -> 03';
					break 2;
				}
			}

			// Step labels / single page section titles: Your Details, Stops, Submit.
			foreach ($ggtOrder as $index => $key) {
				foreach ($groups[$key] as &$child) {
					$attrs = &$child['attrs'];

					if (($child['blockName'] ?? '') === 'eightshift-forms/step' && ($attrs['stepStepLabel'] ?? '') !== $ggtLabels[$key]) {
						$attrs['stepStepLabel'] = $ggtLabels[$key];
						$notes[] = sprintf('step %02d label -> %s', $index + 1, $ggtLabels[$key]);
					}

					foreach ($attrs as $attrKey => $value) {
						if (str_ends_with($attrKey, 'FieldAttrs') && is_array($value) && isset($value['data-section-title']) && $value['data-section-title'] !== $ggtLabels[$key]) {
							$attrs[$attrKey]['data-section-title'] = $ggtLabels[$key];
							$notes[] = sprintf('section %02d title -> %s', $index + 1, $ggtLabels[$key]);
						}
					}
					unset($attrs);
				}
				unset($child);
			}

			$lastIsSubmit = (end($inner)['blockName'] ?? '') === 'eightshift-forms/submit';

			if ($seen !== $ggtOrder || ($tail && !$lastIsSubmit)) {
				array_unshift($notes, implode(' > ', $seen) . ' => ' . implode(' > ', $ggtOrder));
			}

			if (!$notes) {
				continue;
			}

			foreach ($ggtOrder as $index => $key) {
				foreach ($groups[$key] as &$child) {
					$attrs = &$child['attrs'];

					if (($child['blockName'] ?? '') === 'eightshift-forms/step') {
						unset($attrs['stepStepPrevLabel'], $attrs['stepStepNextLabel']);

						if ($index > 0) {
							$attrs['stepStepPrevLabel'] = 'Back';
						}
						if ($index < count($ggtOrder) - 1) {
							$attrs['stepStepNextLabel'] = 'Continue';
						}
					}

					foreach ($attrs as $attrKey => $value) {
						if (str_ends_with($attrKey, 'FieldAttrs') && is_array($value) && isset($value['data-section-number'])) {
							$attrs[$attrKey]['data-section-number'] = sprintf('%02d', $index + 1);
						}
					}
					unset($attrs);
				}
				unset($child);
			}

			$reordered = array_merge($groups['details'], $groups['stops'], $groups['service'], $tail);

			if (count($reordered) !== count($inner)) {
				WP_CLI::warning('Block count mismatch; form left as is.');
				return;
			}

			// innerContent holds one null per inner block, in order: same count, so the new order serializes as is.
			$block['innerBlocks'] = $reordered;
			$note = implode('; ', $notes);
		}
		unset($block);
	};
	$walk($blocks);

	if ($note === '') {
		continue;
	}

	$content = serialize_blocks($blocks);

	if ($ggtDryRun) {
		WP_CLI::log(sprintf('[dry-run] Form %d "%s": would change %s.', $ggtForm->ID, $ggtForm->post_title, $note));
		continue;
	}

	$result = wp_update_post(wp_slash(['ID' => $ggtForm->ID, 'post_content' => $content]), true);

	if (is_wp_error($result)) {
		WP_CLI::error("Form {$ggtForm->ID}: " . $result->get_error_message());
	}

	WP_CLI::log(sprintf('Form %d "%s": changed %s.', $ggtForm->ID, $ggtForm->post_title, $note));
	$ggtChanged++;
}

WP_CLI::success($ggtDryRun ? 'Dry run done.' : ($ggtChanged ? "{$ggtChanged} form(s) updated (revisions kept)." : 'Nothing to update.'));

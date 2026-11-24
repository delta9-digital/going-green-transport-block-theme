<?php

/**
 * Quote forms: ready for Going Green's POST /quote endpoint (2026-11-19 contract).
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/forms-quote-endpoint.php [dry-run]
 *
 * Saved Forms posts keep their own copy of each block's settings. This brings the quote forms in line with the
 * Going Green integration in the forms plugin (delta9-digital-forms, Goinggreen.php):
 * - pickups / drop-offs: hidden place_lat and place_lng sub-fields after place_id, filled by a picked Google Places
 *   suggestion (autocompleteFill lat / lng); the endpoint gets them beside the place_id as a cross-check;
 * - one pickup per quote (a second pickup is a different licence, company and manifest, so a different run);
 * - up to 25 drop-offs (the endpoint's limit);
 * - service tier copy says what the difference is, not a number of days (the lead time is a server-side value).
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

$ggtTierCopy = [
	'on_demand' => [
		'Your schedule. Hourly pricing, today&#039;s standard turnaround.',
		'Your schedule. We go when you need us, at an hourly rate that costs more for exactly that reason.',
	],
	'flexible' => [
		'Our schedule. Flat rate per drop-off, needs 4-5 business days to get on the schedule.',
		'Our schedule. A flat rate per drop-off on a route we&#039;re already driving, so it needs some notice to get on it.',
	],
];

$ggtForms = get_posts(['post_type' => 'eightshift-forms', 'post_status' => 'any', 'numberposts' => -1]);
$ggtChanged = 0;

foreach ($ggtForms as $ggtForm) {
	$blocks = parse_blocks($ggtForm->post_content);
	$changes = [];

	$walk = static function (array &$list) use (&$walk, &$changes, $ggtTierCopy): void {
		foreach ($list as &$block) {
			$name = $block['blockName'] ?? '';

			if ($name === 'eightshift-forms/repeater' && in_array($block['attrs']['repeaterRepeaterName'] ?? '', ['pickups', 'dropoffs'], true)) {
				$attrs = &$block['attrs'];
				$before = wp_json_encode($attrs);
				$subfields = [];

				foreach ($attrs['repeaterRepeaterSubfields'] ?? [] as $subfield) {
					$subName = $subfield['name'] ?? '';

					if (in_array($subName, ['place_lat', 'place_lng'], true)) {
						continue;
					}

					if ($subName === 'address' && ($subfield['autocomplete'] ?? '') === 'places') {
						$subfield['autocompleteFill'] = array_merge(
							(array) ($subfield['autocompleteFill'] ?? []),
							['lat' => 'place_lat', 'lng' => 'place_lng']
						);
					}

					$subfields[] = $subfield;

					if ($subName === 'place_id') {
						$subfields[] = ['name' => 'place_lat', 'type' => 'hidden'];
						$subfields[] = ['name' => 'place_lng', 'type' => 'hidden'];
					}
				}

				$attrs['repeaterRepeaterSubfields'] = $subfields;
				$attrs['repeaterRepeaterMaxCount'] = $attrs['repeaterRepeaterName'] === 'pickups' ? 1 : 25;

				if (wp_json_encode($attrs) !== $before) {
					$changes[] = $attrs['repeaterRepeaterName'];
				}
				unset($attrs);
			}

			if ($name === 'eightshift-forms/radio' && isset($ggtTierCopy[$block['attrs']['radioRadioValue'] ?? ''])) {
				[$old, $new] = $ggtTierCopy[$block['attrs']['radioRadioValue']];
				$label = (string) ($block['attrs']['radioRadioLabel'] ?? '');

				foreach ([$old, html_entity_decode($old, ENT_QUOTES)] as $needle) {
					if (str_contains($label, $needle)) {
						$block['attrs']['radioRadioLabel'] = str_replace($needle, $needle === $old ? $new : html_entity_decode($new, ENT_QUOTES), $label);
						$changes[] = 'tier ' . $block['attrs']['radioRadioValue'];
						break;
					}
				}
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

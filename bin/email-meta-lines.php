<?php

/**
 * Route-story email cards: make the date line ("Tue, Oct 20 · 8:31 AM · 1 of 1") stackable.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/email-meta-lines.php [dry-run]
 *
 * On phones the line wrapped mid-phrase ("… · 1 of / 1"). Each matching paragraph (mono font, ending in
 * "· n of m") gets the class ggt-email-meta and its text split into spans:
 *   <span class="ggt-email-meta__when">Tue, Oct 20 · 8:31 AM</span><span class="ggt-email-meta__sep"> · </span>
 *   <span class="ggt-email-meta__count">1 of 1</span>
 * The theme (_route-scene.scss) keeps it on one line on wide screens and stacks when / count on phones.
 *
 * All published posts, pages and reusable blocks. Idempotent; wp_update_post keeps a revision.
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

$ggtDryRun = in_array('dry-run', $args ?? [], true);

global $wpdb;

$ggtIds = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('revision','attachment') AND post_content LIKE '%has-mono-font-family%' AND post_content REGEXP '[0-9]+ of [0-9]+</p>'");

// A paragraph block whose <p> uses the mono font and ends in "· n of m".
$ggtPattern = '#<!-- wp:paragraph (\{[^}]*(?:\{[^}]*\}[^}]*)*\}) -->\n<p class="([^"]*has-mono-font-family[^"]*)"([^>]*)>([^<]+?) · (\d+ of \d+)</p>\n<!-- /wp:paragraph -->#u';

$ggtTotal = 0;

foreach ($ggtIds as $ggtId) {
	$ggtPost = get_post((int) $ggtId);
	$ggtCount = 0;

	$ggtNew = preg_replace_callback($ggtPattern, static function ($m) use (&$ggtCount) {
		$attrs = json_decode($m[1], true);

		if (!is_array($attrs) || strpos((string) ($attrs['className'] ?? ''), 'ggt-email-meta') !== false) {
			return $m[0];
		}

		$attrs['className'] = trim(($attrs['className'] ?? '') . ' ggt-email-meta');
		$ggtCount++;

		return '<!-- wp:paragraph ' . wp_json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . " -->\n"
			. '<p class="ggt-email-meta ' . $m[2] . '"' . $m[3] . '>'
			. '<span class="ggt-email-meta__when">' . $m[4] . '</span>'
			. '<span class="ggt-email-meta__sep"> · </span>'
			. '<span class="ggt-email-meta__count">' . $m[5] . '</span>'
			. "</p>\n<!-- /wp:paragraph -->";
	}, $ggtPost->post_content);

	if (!$ggtCount) {
		continue;
	}

	$ggtTotal += $ggtCount;

	if ($ggtDryRun) {
		WP_CLI::log("[dry-run] {$ggtPost->post_type} {$ggtPost->ID} /{$ggtPost->post_name}: {$ggtCount} date line(s).");
		continue;
	}

	$ggtResult = wp_update_post(wp_slash(['ID' => $ggtPost->ID, 'post_content' => $ggtNew]), true);

	if (is_wp_error($ggtResult)) {
		WP_CLI::warning("{$ggtPost->ID}: " . $ggtResult->get_error_message());
		continue;
	}

	WP_CLI::log("{$ggtPost->post_type} {$ggtPost->ID} /{$ggtPost->post_name}: {$ggtCount} date line(s) updated.");
}

WP_CLI::success(($ggtDryRun ? '[dry-run] ' : '') . "{$ggtTotal} date line(s).");

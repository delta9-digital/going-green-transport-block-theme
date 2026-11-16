<?php

/**
 * Launch SEO fixes (staging SEO plan, Phase 0). Idempotent: run on local (content source of truth)
 * and on any copy pushed before this ran.
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/launch-seo-fixes.php                dry run: lists what would change
 *   wp eval-file bin/launch-seo-fixes.php apply          change it
 *   wp eval-file bin/launch-seo-fixes.php apply og-id=<n>  import the social image with this ID (keep env IDs aligned)
 *   ... og-file=<path>                                   social image from another path than the theme's assets/
 * Then: wp yoast index --reindex --skip-confirmation && wp cron event run wpseo_llms_txt_population
 *
 * 1. Redirection rules: absolute targets on another host (lndo.site) become host-relative; chains
 *    collapse to the final URL; rules on a #fragment (never sent to the server) are deleted; default
 *    query matching "pass", so /contact/?utm_source=x still redirects. redirection_options is not
 *    pushed with content: run this on production after the launch push too.
 * 2. Yoast Site representation: phone, email, legal name, founding year, description, sameAs.
 *    Author archives off (one author), default og:image (assets/images/social/ggt-og-default.jpg),
 *    no site name after post titles.
 * 3. quote-thank-you: noindex.
 * 4. Meta descriptions for pages and category archives that had none, trims over 155 characters.
 * 5. Featured image on every location without a raster one (the round-3 SVG placeholders): the first
 *    slide of its hero carousel. Templates don't show it; Yoast uses it for og:image.
 * 6. Internal links that go through a 301 (old /services/… paths, /cannabis-transport-locations-mn/,
 *    the short license-requirements slug) point at the final URL, host-relative. Published posts,
 *    pages and locations only; written with $wpdb (no revision, post_modified unchanged).
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

$ggtApply = isset($ggtOpts['apply']);
$ggtChanges = 0;
$ggtLog = static function (string $message) use ($ggtApply, &$ggtChanges): void {
	$ggtChanges++;
	WP_CLI::log(($ggtApply ? '  ' : '  [dry run] ') . $message);
};

global $wpdb;

// Every host the site has lived on: links on these hosts are internal.
$ggtHosts = '(?:www\.)?(?:goinggreentransport\.com|going-green-transport\.lndo\.site|goinggreentstg\.wpenginepowered\.com|goinggreentstg\.wpengine\.com)';

// Final URL for paths that redirect (lowercase, no trailing slash).
$ggtFinal = [
	'/cannabis-transport-locations-mn' => '/locations/',
	'/services/cultivation-production-in-minnesota' => '/cultivation-production-in-minnesota/',
	'/services/lab-sample-transport-in-minnesota' => '/lab-sample-transport-in-minnesota/',
	'/services/dispensary-transportation-in-minnesota' => '/dispensary-transportation-in-minnesota/',
	'/services/lab-sample-courier' => '/lab-sample-transport-in-minnesota/',
	'/minnesota-cannabis-transport-license-requirements' => '/minnesota-cannabis-transport-license-requirements-what-cultivators-need-to-verify/',
	'/cannabis/minnesota-cannabis-transport-license' => '/minnesota-cannabis-transport-license-requirements-what-cultivators-need-to-verify/',
	'/cannabis/minnesota-cannabis-transport-license-requirements-what-cultivators-need-to-verify' => '/minnesota-cannabis-transport-license-requirements-what-cultivators-need-to-verify/',
	'/contact' => '/get-a-quote/',
	'/contact-us' => '/get-a-quote/',
];

$ggtResolve = static function (string $path) use ($ggtFinal): string {
	$key = strtolower(rtrim($path, '/'));

	return $ggtFinal[$key] ?? $path;
};

// 1. Redirection rules.
WP_CLI::log('1. Redirection rules');
$ggtTable = $wpdb->prefix . 'redirection_items';
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ggtTable)) === $ggtTable) {
	foreach ($wpdb->get_results("SELECT id, url, action_data FROM {$ggtTable} WHERE action_type = 'url' ORDER BY id") as $ggtRule) {
		if (str_starts_with($ggtRule->url, '/#')) {
			$ggtLog("delete rule {$ggtRule->id} {$ggtRule->url} (a #fragment never reaches the server)");
			$ggtApply && $wpdb->delete($ggtTable, ['id' => (int) $ggtRule->id]);
			continue;
		}

		$ggtTarget = (string) preg_replace('~^https?://' . $ggtHosts . '(?=/|$)~i', '', $ggtRule->action_data);
		$ggtTarget = $ggtTarget === '' ? '/' : $ggtTarget;
		if (str_starts_with($ggtTarget, '/')) {
			$ggtTarget = $ggtResolve($ggtTarget);
		}

		if ($ggtTarget !== $ggtRule->action_data) {
			$ggtLog("rule {$ggtRule->id} {$ggtRule->url}: {$ggtRule->action_data} -> {$ggtTarget}");
			$ggtApply && $wpdb->update($ggtTable, ['action_data' => $ggtTarget], ['id' => (int) $ggtRule->id]);
		}
	}
	// Old URLs arrive with tracking parameters (?utm_source=…, ?fbclid=…): with "exact" query matching
	// those miss the rule and 404. "pass" ignores the query when matching and passes it to the target.
	$ggtRedirection = get_option('redirection_options');
	if (is_array($ggtRedirection) && ($ggtRedirection['flag_query'] ?? 'exact') !== 'pass') {
		$ggtLog('Redirection default query matching: ' . ($ggtRedirection['flag_query'] ?? 'exact') . ' -> pass');
		$ggtRedirection['flag_query'] = 'pass';
		$ggtApply && update_option('redirection_options', $ggtRedirection);
	}
} else {
	WP_CLI::log('  no Redirection table');
}

// 2. Yoast settings.
WP_CLI::log('2. Yoast settings');
$ggtYoast = [
	'org-phone' => '+1-612-208-9557',
	'org-email' => 'service@goinggreentransport.com',
	'org-legal-name' => 'Going Green Transport LLC',
	'org-founding-date' => '2023',
	'org-description' => "Minnesota's first dedicated cannabis transport company, providing same-day, statewide, compliance-first B2B cannabis logistics for cultivators, manufacturers, testing labs, and dispensaries.",
	'facebook_site' => 'https://www.facebook.com/61584143425787',
	// LinkedIn (design kit URL) left out until the client confirms it; add the Google Business Profile URL here when known.
	'other_social_urls' => ['https://www.instagram.com/goinggreentransportmn/'],
	'disable-author' => true,
	'noindex-author-wpseo' => true,
	// Post titles ran to 113 characters with " - Going Green Transport LLC"; pages and locations keep it.
	'title-post' => '%%title%% %%page%%',
];

// Default social image, imported once from the theme.
$ggtOgId = (int) (get_posts([
	'post_type' => 'attachment',
	'post_status' => 'inherit',
	'meta_key' => '_ggt_og_default',
	'meta_value' => '1',
	'fields' => 'ids',
	'numberposts' => 1,
])[0] ?? 0);
if (!$ggtOgId) {
	$ggtOgFile = (string) ($ggtOpts['og-file'] ?? get_theme_file_path('assets/images/social/ggt-og-default.jpg'));
	$ggtLog('import default social image ' . basename($ggtOgFile) . (isset($ggtOpts['og-id']) ? " as ID {$ggtOpts['og-id']}" : ''));
	if ($ggtApply) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$ggtTmp = wp_tempnam('ggt-og-default.jpg');
		copy($ggtOgFile, $ggtTmp);
		$ggtOgId = media_handle_sideload(
			['name' => 'ggt-og-default.jpg', 'tmp_name' => $ggtTmp],
			0,
			'Going Green Transport: cannabis transport across Minnesota',
			['import_id' => (int) ($ggtOpts['og-id'] ?? 0)]
		);
		if (is_wp_error($ggtOgId)) {
			WP_CLI::error($ggtOgId->get_error_message());
		}
		update_post_meta($ggtOgId, '_ggt_og_default', '1');
		update_post_meta($ggtOgId, '_wp_attachment_image_alt', 'Going Green Transport driver loading a sealed tote into a secure cargo van, beside the headline Cannabis Transport Across Minnesota');
		WP_CLI::log("  imported as attachment {$ggtOgId}");
	}
}
if ($ggtOgId) {
	$ggtYoast['og_default_image_id'] = $ggtOgId;
	$ggtYoast['og_default_image'] = (string) wp_get_attachment_url($ggtOgId);
}

foreach ($ggtYoast as $ggtKey => $ggtVal) {
	if (WPSEO_Options::get($ggtKey) !== $ggtVal) {
		$ggtLog("Yoast {$ggtKey} = " . (is_array($ggtVal) ? implode(', ', $ggtVal) : var_export($ggtVal, true)));
		$ggtApply && WPSEO_Options::set($ggtKey, $ggtVal);
	}
}

// 3 + 4. Page robots and meta descriptions (path => [description, overwrite existing]).
WP_CLI::log('3. Thank-you page noindex');
$ggtThanks = get_page_by_path('quote-thank-you');
if ($ggtThanks && get_post_meta($ggtThanks->ID, '_yoast_wpseo_meta-robots-noindex', true) !== '1') {
	$ggtLog("noindex page {$ggtThanks->ID} quote-thank-you");
	$ggtApply && update_post_meta($ggtThanks->ID, '_yoast_wpseo_meta-robots-noindex', '1');
}

WP_CLI::log('4. Meta descriptions');
$ggtDescriptions = [
	'how-it-works' => "How Going Green Transport moves cannabis in Minnesota: a direct On-Demand run or a stop on your zone's Regional Route. GPS-tracked, Metrc-aligned.",
	'how-it-works/regional-routes' => 'Five Minnesota zones, each with its own route day. Book a stop on Central, Northeast, Southeast, Northwest or Southwest routes at a flat $150 or $250.',
	'on-demand-transport' => 'A direct, GPS-tracked cannabis transport run on your schedule: a two-person team drives straight from your pickup to your drop-off anywhere in Minnesota.',
	'flat-rate-transport' => 'Flat-rate cannabis transport in Minnesota: $150 from the Twin Cities metro to St. Cloud, $250 beyond 150 miles. Same-day delivery, Metrc-aligned.',
	'partner-program' => 'Licensed transporters, wholesalers and micro- and mezzobusinesses: hand your Minnesota cannabis transport to us and earn referral fees at partner rates.',
	'terms-and-conditions' => 'Terms and conditions for Going Green Transport LLC: the policies, user rights and legal terms that govern our Minnesota cannabis transport services.',
];
foreach ($ggtDescriptions as $ggtPath => $ggtDesc) {
	if (mb_strlen($ggtDesc) > 155) {
		WP_CLI::error("description for {$ggtPath} is " . mb_strlen($ggtDesc) . ' characters');
	}
	$ggtPage = get_page_by_path($ggtPath);
	if (!$ggtPage) {
		WP_CLI::warning("no page {$ggtPath}");
		continue;
	}
	$ggtOld = (string) get_post_meta($ggtPage->ID, '_yoast_wpseo_metadesc', true);
	// Keep a description someone wrote unless it is too long.
	if ($ggtOld !== $ggtDesc && ($ggtOld === '' || mb_strlen($ggtOld) > 155)) {
		$ggtLog("page {$ggtPage->ID} {$ggtPath} (" . mb_strlen($ggtOld) . ' -> ' . mb_strlen($ggtDesc) . " chars)");
		$ggtApply && update_post_meta($ggtPage->ID, '_yoast_wpseo_metadesc', $ggtDesc);
	}
}
$ggtCategoryDescriptions = [
	'cultivators' => 'Guides for Minnesota cannabis cultivators: moving harvests, Metrc manifests, lab samples and compliant transport from grow to manufacturer or dispensary.',
	'manufacturers' => 'Guides for Minnesota cannabis manufacturers: compliant transport of inputs and finished products, Metrc manifests and scheduled runs to retailers.',
	'testing' => 'Guides on cannabis testing transport in Minnesota: lab sample pickup, chain of custody, Metrc manifests and same-day courier runs to licensed labs.',
];
foreach ($ggtCategoryDescriptions as $ggtSlug => $ggtDesc) {
	if (mb_strlen($ggtDesc) > 155) {
		WP_CLI::error("description for category {$ggtSlug} is " . mb_strlen($ggtDesc) . ' characters');
	}
	$ggtTerm = get_term_by('slug', $ggtSlug, 'category');
	if ($ggtTerm && (string) WPSEO_Taxonomy_Meta::get_term_meta($ggtTerm, 'category', 'desc') === '') {
		$ggtLog("category {$ggtSlug}");
		$ggtApply && WPSEO_Taxonomy_Meta::set_value($ggtTerm->term_id, 'category', 'wpseo_desc', $ggtDesc);
	}
}

// 5. Location featured images.
WP_CLI::log('5. Location featured images');
$ggtFirstSlide = static function (array $blocks, bool $inCarousel = false) use (&$ggtFirstSlide): int {
	foreach ($blocks as $block) {
		if ($inCarousel && $block['blockName'] === 'core/image' && !empty($block['attrs']['id'])) {
			return (int) $block['attrs']['id'];
		}
		$id = $ggtFirstSlide($block['innerBlocks'] ?? [], $inCarousel || $block['blockName'] === 'eightshift-boilerplate/carousel');
		if ($id) {
			return $id;
		}
	}

	return 0;
};
foreach (get_posts(['post_type' => 'location', 'post_status' => 'publish', 'numberposts' => -1]) as $ggtLocation) {
	// The SVG placeholders from round 3 are still set as featured images: social cards can't use SVG.
	$ggtThumb = (int) get_post_thumbnail_id($ggtLocation);
	if ($ggtThumb && get_post_mime_type($ggtThumb) !== 'image/svg+xml') {
		continue;
	}
	$ggtSlide = $ggtFirstSlide(parse_blocks($ggtLocation->post_content));
	if (!$ggtSlide) {
		WP_CLI::warning("location {$ggtLocation->ID} {$ggtLocation->post_name}: no hero carousel image");
		continue;
	}
	$ggtLog("location {$ggtLocation->ID} {$ggtLocation->post_name}: featured image {$ggtThumb} -> {$ggtSlide}");
	$ggtApply && set_post_thumbnail($ggtLocation, $ggtSlide);
}

// 6. Internal links through redirects.
WP_CLI::log('6. Internal links that redirect');
$ggtHref = '~href="(?:https?://' . $ggtHosts . ')?(/[^"#?]*)([#?][^"]*)?"~i';
$ggtPosts = $wpdb->get_results("SELECT ID, post_name, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post', 'page', 'location')");
foreach ($ggtPosts as $ggtPost) {
	$ggtFixed = [];
	$ggtContent = preg_replace_callback(
		$ggtHref,
		static function (array $m) use ($ggtResolve, &$ggtFixed): string {
			$final = $ggtResolve($m[1]);
			if ($final === $m[1]) {
				return $m[0];
			}
			$ggtFixed[] = "{$m[1]} -> {$final}";

			return 'href="' . $final . ($m[2] ?? '') . '"';
		},
		$ggtPost->post_content
	);
	if ($ggtFixed) {
		$ggtLog("post {$ggtPost->ID} {$ggtPost->post_name}: " . implode(', ', array_unique($ggtFixed)));
		if ($ggtApply) {
			$wpdb->update($wpdb->posts, ['post_content' => $ggtContent], ['ID' => (int) $ggtPost->ID]);
			clean_post_cache((int) $ggtPost->ID);
		}
	}
}

WP_CLI::success($ggtChanges ? ($ggtApply ? "{$ggtChanges} change(s) made" : "{$ggtChanges} change(s) to make (rerun with apply)") : 'nothing to change');

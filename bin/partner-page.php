<?php

/**
 * Create or update the Partner Program page (/partner-program/).
 *
 * Usage (WP-CLI):
 *   wp eval-file bin/partner-page.php [dry-run] [status=draft|publish]
 *
 * Audience: licensed transporters, wholesalers and micro/mezzobusinesses that can move their own
 * product but would rather hand the runs to us (or refer clients) for referral fees and partner terms.
 * Sections follow the Flat-Rate page: lime hero card + Minnesota cannabis icon on a forest panel, white icon cards, numbered steps, and
 * the application form (`[ggt_partner_form]`, src/Partner/PartnerForm.php): intro, then the form card full width.
 * Order: hero, how it works, apply (form), who it's for, what partners get.
 *
 * Images are looked up by file path, so the script runs unchanged on local and staging. Idempotent:
 * the page is matched by slug and its content replaced. New pages default to draft.
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

const GGT_PARTNER_SLUG = 'partner-program';

/* ---------------------------------------------------------------- helpers */

/**
 * Attachment [id, url] for an uploads-relative path, or [0, ''] when missing.
 */
function ggt_pp_media(string $file): array
{
	global $wpdb;
	$id = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
		$file
	));
	if (!$id) {
		WP_CLI::warning("Missing media: {$file}");
		return [0, ''];
	}
	return [$id, (string) wp_get_attachment_url($id)];
}

function ggt_pp_json(array $attrs): string
{
	// Core serialises "--" in attributes as --; keep it identical so the editor sees no change.
	return str_replace('--', '\\u002d\\u002d', wp_json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/** Highlighted word in a heading. */
function ggt_pp_mark(string $text, string $color = 'green-500'): string
{
	return '<mark style="background-color:rgba(0,0,0,0)" class="has-inline-color has-' . $color . '-color">' . $text . '</mark>';
}

/** Full-width page section wrapping an alignwide inner group. */
function ggt_pp_section(string $name, string $slug, string $pad, string $inner, string $anchor = ''): string
{
	$attrs = ['tagName' => 'section', 'align' => 'full'];
	if ($anchor !== '') {
		$attrs['anchor'] = $anchor;
	}
	$attrs += [
		'className' => 'ggt-sec ggt-sec--' . $slug,
		'style' => ['spacing' => ['margin' => ['top' => '0'], 'padding' => ['top' => 'var:preset|spacing|' . $pad], 'blockGap' => '0']],
		'layout' => ['type' => 'constrained'],
		'metadata' => ['name' => $name],
	];
	$id = $anchor !== '' ? ' id="' . $anchor . '"' : '';

	return '<!-- wp:group ' . ggt_pp_json($attrs) . " -->\n"
		. '<section class="wp-block-group alignfull ggt-sec ggt-sec--' . $slug . '"' . $id . ' style="margin-top:0;padding-top:var(--wp--preset--spacing--' . $pad . ')">'
		. $inner
		. "</section>\n<!-- /wp:group -->";
}

/** Section intro: label, H2, lead (Flat-Rate "Every Transport Includes" header). */
function ggt_pp_intro(string $label, string $heading, string $lead): string
{
	return '<!-- wp:group {"style":{"spacing":{"blockGap":"0","margin":{"bottom":"var:preset|spacing|90"}}},"layout":{"type":"constrained","contentSize":"720px","justifyContent":"left"}} -->' . "\n"
		. '<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--90)">'
		. ggt_pp_label($label, true)
		. "\n\n" . '<!-- wp:heading {"textColor":"green-900","fontSize":"section-md","style":{"typography":{"letterSpacing":"-0.02em","lineHeight":"1.05"},"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}}} -->' . "\n"
		. '<h2 class="wp-block-heading has-green-900-color has-text-color has-section-md-font-size" style="margin-bottom:var(--wp--preset--spacing--30);letter-spacing:-0.02em;line-height:1.05">' . $heading . '</h2>' . "\n"
		. '<!-- /wp:heading -->'
		. "\n\n" . '<!-- wp:paragraph {"textColor":"ink-muted","fontSize":"lead"} -->' . "\n"
		. '<p class="has-ink-muted-color has-text-color has-lead-font-size">' . $lead . '</p>' . "\n"
		. '<!-- /wp:paragraph -->'
		. "</div>\n<!-- /wp:group -->";
}

/** Grey uppercase section label. */
function ggt_pp_label(string $text, bool $spaced = false): string
{
	$attrs = '{"textColor":"gray-600","fontFamily":"heading","fontSize":"small","className":"is-style-ggt-label","style":{"typography":{"fontWeight":"600","letterSpacing":"0.12em","textTransform":"uppercase"}'
		. ($spaced ? ',"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}' : '') . '}}';
	$style = ($spaced ? 'margin-bottom:var(--wp--preset--spacing--30);' : '') . 'font-weight:600;letter-spacing:0.12em;text-transform:uppercase';

	return '<!-- wp:paragraph ' . $attrs . ' -->' . "\n"
		. '<p class="is-style-ggt-label has-gray-600-color has-text-color has-heading-font-family has-small-font-size" style="' . $style . '">' . $text . '</p>' . "\n"
		. '<!-- /wp:paragraph -->';
}

/** White card: icon on a lime plate (or a step number), H3, body. */
function ggt_pp_card(string $title, string $body, array $icon = [], string $number = ''): string
{
	$top = '';
	if ($icon && $icon[0]) {
		$top = '<!-- wp:group {"backgroundColor":"lime-500","className":"ggt-plate ggt-plate\u002d\u002d64","style":{"spacing":{"blockGap":"0","margin":{"bottom":"var:preset|spacing|50"}},"border":{"radius":"50%"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center","verticalAlignment":"center"}} -->' . "\n"
			. '<div class="wp-block-group ggt-plate ggt-plate--64 has-lime-500-background-color has-background" style="border-radius:50%;margin-bottom:var(--wp--preset--spacing--50)">'
			. '<!-- wp:image {"id":' . $icon[0] . ',"width":"48px","height":"48px","sizeSlug":"full","className":"ggt-icon"} -->' . "\n"
			. '<figure class="wp-block-image size-full is-resized ggt-icon"><img src="' . esc_url($icon[1]) . '" alt="" class="wp-image-' . $icon[0] . '" style="width:48px;height:48px"/></figure>' . "\n"
			. '<!-- /wp:image -->'
			. "</div>\n<!-- /wp:group -->\n\n";
	} elseif ($number !== '') {
		$top = '<!-- wp:paragraph {"textColor":"green-500","fontFamily":"heading","style":{"typography":{"fontSize":"clamp(48px, 5vw, 72px)","fontWeight":"700","letterSpacing":"-0.04em","lineHeight":"0.95"},"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}}} -->' . "\n"
			. '<p class="has-green-500-color has-text-color has-heading-font-family" style="margin-bottom:var(--wp--preset--spacing--50);font-size:clamp(48px, 5vw, 72px);font-weight:700;letter-spacing:-0.04em;line-height:0.95">' . $number . '</p>' . "\n"
			. "<!-- /wp:paragraph -->\n\n";
	}

	return '<!-- wp:group {"backgroundColor":"white","style":{"spacing":{"padding":{"top":"36px","right":"var:preset|spacing|80","bottom":"36px","left":"var:preset|spacing|80"},"blockGap":"14px"},"border":{"radius":"24px"},"dimensions":{"minHeight":"280px"}},"layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group has-white-background-color has-background" style="border-radius:24px;min-height:280px;padding-top:36px;padding-right:var(--wp--preset--spacing--80);padding-bottom:36px;padding-left:var(--wp--preset--spacing--80)">'
		. $top
		. '<!-- wp:heading {"level":3,"textColor":"green-900","fontSize":"heading-md"} -->' . "\n"
		. '<h3 class="wp-block-heading has-green-900-color has-text-color has-heading-md-font-size">' . $title . '</h3>' . "\n"
		. "<!-- /wp:heading -->\n\n"
		. '<!-- wp:paragraph {"textColor":"ink-muted","fontSize":"body","style":{"typography":{"lineHeight":"1.6"}}} -->' . "\n"
		. '<p class="has-ink-muted-color has-text-color has-body-font-size" style="line-height:1.6">' . $body . '</p>' . "\n"
		. '<!-- /wp:paragraph -->'
		. "</div>\n<!-- /wp:group -->";
}

/** Grid of cards. */
function ggt_pp_grid(array $cards, string $minWidth, int $columns): string
{
	return '<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"grid","minimumColumnWidth":"' . $minWidth . '","columnCount":' . $columns . '}} -->' . "\n"
		. '<div class="wp-block-group">' . implode("\n\n", $cards) . "</div>\n<!-- /wp:group -->";
}

/** Alignwide wrapper. */
function ggt_pp_wide(string $inner): string
{
	return '<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group alignwide">' . $inner . "</div>\n<!-- /wp:group -->";
}

/* ---------------------------------------------------------------- content */

$mnIcon = ggt_pp_media('2026/10/minnesota-lime.svg');
$icons = [
	'licensed' => ggt_pp_media('2026/10/licensed.svg'),
	'cargo' => ggt_pp_media('2026/10/secure-cargo.svg'),
	'manifest' => ggt_pp_media('2026/10/manifest.svg'),
	'compliance' => ggt_pp_media('2026/10/compliance.svg'),
	'gps' => ggt_pp_media('2026/10/gps-tracking.svg'),
	'same-day' => ggt_pp_media('2026/10/same-day.svg'),
];

// Hero: lime card (label, H1, lead, buttons), as on Flat-Rate, + the Minnesota cannabis icon
// (lime state, leaf cut out) centred on a forest-green panel. Three-column grid: the card spans two.
$hero = ggt_pp_section('Hero', 'hero', '40',
	'<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"grid","minimumColumnWidth":"320px","columnCount":3}} -->' . "\n"
	. '<div class="wp-block-group alignwide">'
	. '<!-- wp:group {"backgroundColor":"lime-500","textColor":"green-900","style":{"spacing":{"padding":{"top":"clamp(32px, 5vw, 64px)","right":"clamp(32px, 5vw, 64px)","bottom":"clamp(32px, 5vw, 64px)","left":"clamp(32px, 5vw, 64px)"},"blockGap":"var:preset|spacing|90"},"border":{"radius":"24px"},"dimensions":{"minHeight":"460px"},"layout":{"columnSpan":2}},"layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group has-green-900-color has-lime-500-background-color has-text-color has-background" style="border-radius:24px;min-height:460px;padding-top:clamp(32px, 5vw, 64px);padding-right:clamp(32px, 5vw, 64px);padding-bottom:clamp(32px, 5vw, 64px);padding-left:clamp(32px, 5vw, 64px)">'
	. '<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group">'
	. '<!-- wp:paragraph {"textColor":"green-700","fontFamily":"heading","fontSize":"small","style":{"typography":{"fontWeight":"700","letterSpacing":"0.12em","textTransform":"uppercase"},"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}}} -->' . "\n"
	. '<p class="has-green-700-color has-text-color has-heading-font-family has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--50);font-weight:700;letter-spacing:0.12em;text-transform:uppercase">Partner Program</p>' . "\n"
	. "<!-- /wp:paragraph -->\n\n"
	. '<!-- wp:heading {"level":1,"textColor":"green-900","style":{"typography":{"fontSize":"clamp(44px, 6vw, 88px)","letterSpacing":"-0.025em","lineHeight":"1"},"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}}} -->' . "\n"
	. '<h1 class="wp-block-heading has-green-900-color has-text-color" style="margin-bottom:var(--wp--preset--spacing--60);font-size:clamp(44px, 6vw, 88px);letter-spacing:-0.025em;line-height:1">Partner With ' . ggt_pp_mark('Going Green.') . '</h1>' . "\n"
	. "<!-- /wp:heading -->\n\n"
	. '<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained","contentSize":"560px","justifyContent":"left"}} -->' . "\n"
	. '<div class="wp-block-group">'
	. '<!-- wp:paragraph {"textColor":"green-800","fontSize":"lead-lg","style":{"typography":{"lineHeight":"1.6"}}} -->' . "\n"
	. '<p class="has-green-800-color has-text-color has-lead-lg-font-size" style="line-height:1.6">For licensed transporters, wholesalers, and micro- and mezzobusinesses that can move their own product but would rather not. Hand us the runs, keep your team on the work that grows your business, and earn a referral fee on every client you send our way.</p>' . "\n"
	. '<!-- /wp:paragraph -->'
	. "</div>\n<!-- /wp:group -->"
	. "</div>\n<!-- /wp:group -->\n\n"
	. '<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->' . "\n"
	. '<div class="wp-block-buttons">'
	. '<!-- wp:button {"className":"is-style-ggt-split-light"} -->' . "\n"
	. '<div class="wp-block-button is-style-ggt-split-light"><a class="wp-block-button__link wp-element-button" href="#apply">Apply to Partner</a></div>' . "\n"
	. "<!-- /wp:button -->\n\n"
	. '<!-- wp:button {"className":"is-style-ggt-text"} -->' . "\n"
	. '<div class="wp-block-button is-style-ggt-text"><a class="wp-block-button__link wp-element-button" href="#how">How It Works</a></div>' . "\n"
	. '<!-- /wp:button -->'
	. "</div>\n<!-- /wp:buttons -->"
	. "</div>\n<!-- /wp:group -->\n\n"
	. '<!-- wp:group {"backgroundColor":"green-800","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","right":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|80"},"blockGap":"0"},"border":{"radius":"24px"},"dimensions":{"minHeight":"220px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center","verticalAlignment":"center"}} -->' . "\n"
	. '<div class="wp-block-group has-green-800-background-color has-background" style="border-radius:24px;min-height:220px;padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--80)">'
	. '<!-- wp:image {"id":' . $mnIcon[0] . ',"width":"180px","sizeSlug":"full","className":"ggt-partner-mn"} -->' . "\n"
	. '<figure class="wp-block-image size-full is-resized ggt-partner-mn"><img src="' . esc_url($mnIcon[1]) . '" alt="Minnesota state outline with a cannabis leaf" class="wp-image-' . $mnIcon[0] . '" style="width:180px"/></figure>' . "\n"
	. '<!-- /wp:image -->'
	. "</div>\n<!-- /wp:group -->"
	. "</div>\n<!-- /wp:group -->"
);

$who = ggt_pp_section('Audience', 'partner-who', '140', ggt_pp_wide(
	ggt_pp_intro('Who It\'s For', 'You Could Drive It. ' . ggt_pp_mark('You Don\'t Have To.'), 'You have the license, maybe even the van. What you don\'t have is the hours. Our partners hand us the runs they can\'t staff and keep their people on the work only they can do.')
	. "\n\n"
	. ggt_pp_grid([
		ggt_pp_card('Licensed Transporters', 'Overflow days, out-of-zone stops and routes that don\'t pay for your crew. Pass them to us instead of turning a client away.', $icons['cargo']),
		ggt_pp_card('Wholesalers', 'Keep your team sourcing and selling. We run the pickups from producers and the drops to retailers on your schedule.', $icons['manifest']),
		ggt_pp_card('Microbusinesses', 'Your license lets you move your own product. That doesn\'t mean you have time to. Send us the runs while you grow, make and sell.', $icons['licensed']),
		ggt_pp_card('Mezzobusinesses', 'More volume and more stops on the same small team. Hand us the routine runs and keep your people where they\'re needed.', $icons['compliance']),
	], '240px', 4)
), 'who');

$perks = ggt_pp_section('What Partners Get', 'partner-perks', '140', ggt_pp_wide(
	ggt_pp_intro('What Partners Get', 'Referral Fees and ' . ggt_pp_mark('Better Terms.'), 'Partnering with us should pay, whether you send us your own runs, your clients, or both.')
	. "\n\n"
	. ggt_pp_grid([
		ggt_pp_card('Referral fees', 'Send a business our way and earn a referral fee on the transport they book with us. The fee and payout schedule are agreed up front, in writing.', $icons['licensed']),
		ggt_pp_card('Partner rates and terms', 'Favorable pricing on the runs you hand off, with payment terms that fit how your business runs. No onboarding fees.', $icons['manifest']),
		ggt_pp_card('Run like it\'s yours', 'Two-person teams, GPS-tracked locked cargo and Metrc-aligned manifests on every run, so your name stays good with the people you serve.', $icons['gps']),
	], '280px', 3)
));

$how = ggt_pp_section('How It Works', 'partner-how', '130', ggt_pp_wide(
	ggt_pp_intro('How It Works', 'Three Steps to ' . ggt_pp_mark('Hand It Off.'), 'Simple to start, and nothing to sign until the terms work for you.')
	. "\n\n"
	. ggt_pp_grid([
		ggt_pp_card('Apply', 'Tell us about your business and how you want to work together. It takes about two minutes.', [], '01'),
		ggt_pp_card('Agree your terms', 'We call within one business day to set your referral fee, partner rates and payment terms.', [], '02'),
		ggt_pp_card('Hand it off', 'Send us runs or referrals. We pick up, carry the manifest and deliver the same day. You get paid.', [], '03'),
	], '280px', 3)
), 'how');

$apply = ggt_pp_section('Apply', 'partner-apply', '140',
	'<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group alignwide">'
	. '<!-- wp:group {"style":{"spacing":{"blockGap":"0","margin":{"bottom":"var:preset|spacing|90"}}},"layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--90)">'
	. ggt_pp_label('Apply', true)
	. "\n\n" . '<!-- wp:heading {"textColor":"green-900","fontSize":"section-md","style":{"typography":{"letterSpacing":"-0.02em","lineHeight":"1.05"},"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}}} -->' . "\n"
	. '<h2 class="wp-block-heading has-green-900-color has-text-color has-section-md-font-size" style="margin-bottom:var(--wp--preset--spacing--50);letter-spacing:-0.02em;line-height:1.05">Become a ' . ggt_pp_mark('Partner.') . '</h2>' . "\n"
	. "<!-- /wp:heading -->\n\n"
	. '<!-- wp:paragraph {"textColor":"ink-muted","fontSize":"lead","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}}} -->' . "\n"
	. '<p class="has-ink-muted-color has-text-color has-lead-font-size" style="margin-bottom:var(--wp--preset--spacing--60)">Tell us who you are and what you need. No commitment and no onboarding fees: we\'ll reach out to talk through referral fees and partner terms.</p>' . "\n"
	. "<!-- /wp:paragraph -->\n\n"
	. '<!-- wp:paragraph {"textColor":"green-900","fontSize":"body-md","style":{"typography":{"lineHeight":"1.6"}}} -->' . "\n"
	. '<p class="has-green-900-color has-text-color has-body-md-font-size" style="line-height:1.6"><strong>Rather talk it through?</strong><br>Call <a href="tel:6122089557">612-208-9557</a> or email <a href="mailto:service@goinggreentransport.com">service@goinggreentransport.com</a>.</p>' . "\n"
	. '<!-- /wp:paragraph -->'
	. "</div>\n<!-- /wp:group -->\n\n"
	. '<!-- wp:group {"backgroundColor":"white","style":{"spacing":{"padding":{"top":"clamp(24px, 4vw, 48px)","right":"clamp(24px, 4vw, 48px)","bottom":"clamp(24px, 4vw, 48px)","left":"clamp(24px, 4vw, 48px)"},"blockGap":"0"},"border":{"radius":"24px"}},"layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group has-white-background-color has-background" style="border-radius:24px;padding-top:clamp(24px, 4vw, 48px);padding-right:clamp(24px, 4vw, 48px);padding-bottom:clamp(24px, 4vw, 48px);padding-left:clamp(24px, 4vw, 48px)">'
	. "<!-- wp:shortcode -->\n[ggt_partner_form]\n<!-- /wp:shortcode -->"
	. "</div>\n<!-- /wp:group -->"
	. "</div>\n<!-- /wp:group -->",
	'apply'
);

$content = implode("\n\n", [$hero, $how, $apply, $who, $perks]);

/* ---------------------------------------------------------------- save */

if (isset($ggtOpts['dry-run'])) {
	echo $content, "\n";
	return;
}

$existing = get_page_by_path(GGT_PARTNER_SLUG, OBJECT, 'page');
$postarr = [
	'post_type' => 'page',
	'post_name' => GGT_PARTNER_SLUG,
	'post_title' => 'Partner Program',
	'post_content' => wp_slash($content),
	'post_excerpt' => 'Referral fees and partner terms for Minnesota cannabis transporters, wholesalers, microbusinesses and mezzobusinesses that would rather hand their transport to Going Green Transport.',
];
if ($existing) {
	$postarr['ID'] = $existing->ID;
	// wp_insert_post() would reset an update to draft: keep the current status unless asked.
	$postarr['post_status'] = (string) ($ggtOpts['status'] ?? $existing->post_status);
} else {
	$postarr['post_status'] = (string) ($ggtOpts['status'] ?? 'draft');
}

$id = wp_insert_post($postarr, true);
if (is_wp_error($id)) {
	WP_CLI::error($id->get_error_message());
}

// Yoast SEO, when active.
update_post_meta($id, '_yoast_wpseo_title', 'Cannabis Transport Partner Program | Going Green Transport');
update_post_meta($id, '_yoast_wpseo_metadesc', 'Licensed transporters, wholesalers and micro- and mezzobusinesses: hand your Minnesota cannabis transport to us and earn referral fees with partner rates and terms.');

WP_CLI::success(($existing ? 'Updated' : 'Created') . " page {$id}: " . get_permalink($id) . ' (' . get_post_status($id) . ')');

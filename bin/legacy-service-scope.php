<?php

/**
 * Fence the three legacy service pages' inline CSS into their own content (mobile audit, package G).
 *
 * Usage (WP-CLI; dry run unless `apply` is given):
 *   wp eval-file bin/legacy-service-scope.php [apply] [page=<id|slug>] [diff]
 *
 * Dispensary, Lab Sample and Cultivation are single raw `core/html` blocks migrated from the old site:
 * a whole HTML document (`<head>`, fonts, a ~12 KB `<style>`, `<body>`). Their CSS is global (`*`,
 * `body`, `nav`, `:root`), so it restyles the theme header and footer on those pages: the footer
 * menus become 72px sticky white strips, the page turns white, "Get a Quote" wraps. This:
 *
 * - drops the document-level tags a page body ignores anyway (`<head>`, `<body>`, `<meta charset>`,
 *   `<meta viewport>`) and wraps the markup in one `<div class="ggt-legacy alignfull">` (full width,
 *   so the sections' `5%` paddings keep resolving against the page width as they did before);
 * - rewrites every selector of the inline CSS under `.ggt-legacy` with a small tokenizer
 *   (`*` → `.ggt-legacy *`, `body`/`html`/`:root` → `.ggt-legacy`, `nav` → `.ggt-legacy nav`,
 *   inside @media/@supports too; @keyframes, @font-face and @import are left as they are), and
 *   checks the result has no selector left that could match outside the wrapper;
 * - appends a fenced block of fixes (regenerated on every run): keep the theme body font the pages
 *   always rendered with, a readable mailto link, and phone layout (full-bleed sections, one inset
 *   instead of three, icon and step-number columns stacked above the text, full-width buttons);
 * - restores the contact line a local search-replace rewrote (`service@<this site's host>` and the
 *   bare host back to goinggreentransport.com) and makes the plain-text address a `mailto:` link.
 *   Visible text only: hrefs stay on the current environment's URLs. No other wording changes.
 *
 * Idempotent: an already wrapped page is not re-wrapped, scoped selectors are left alone and the
 * fixes block is replaced, so a second run reports "unchanged". `apply` saves through
 * wp_update_post() with kses off (the content needs its <style>/<link>) and prints the revision IDs
 * before and after, with the one-line rollback (restore the "before" revision).
 *
 * Run it on every environment the pages live on (local, staging, production).
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

const GGT_LEGACY_SCOPE = '.ggt-legacy';
const GGT_LEGACY_PAGES = [
	'dispensary-transportation-in-minnesota',
	'lab-sample-transport-in-minnesota',
	'cultivation-production-in-minnesota',
];
const GGT_LEGACY_DOMAIN = 'goinggreentransport.com';
const GGT_LEGACY_FIX_START = '/* ggt-legacy:fixes start (bin/legacy-service-scope.php rewrites this block) */';
const GGT_LEGACY_FIX_END = '/* ggt-legacy:fixes end */';

/* ---------------------------------------------------------------- CSS scoper */

if (!class_exists('GgtLegacyCssScoper')) {
	/**
	 * Prefix every selector of a style sheet with a scope selector.
	 *
	 * A tokenizer, not a regex over the sheet: it walks the text once, skipping strings and comments,
	 * counting () [] {} so commas and braces inside `:not(a, b)`, `[title="{"]` or `content: "}"` are
	 * never mistaken for structure. Declaration blocks are copied byte for byte.
	 */
	final class GgtLegacyCssScoper
	{
		/** At-rules whose block holds ordinary rules (recurse into them). */
		private const NESTING = ['media', 'supports', 'container', 'layer', 'document', '-moz-document', 'scope'];

		private string $css = '';
		private int $len = 0;
		private int $pos = 0;

		/** @var array{selectors: int, rewritten: array<string, string>, verbatim: array<int, string>} */
		public array $stats = ['selectors' => 0, 'rewritten' => [], 'verbatim' => []];

		/** @var array<int, string> Every selector as found in the input, for the containment check. */
		public array $in = [];

		public function __construct(private string $scope)
		{
		}

		public function scope(string $css): string
		{
			$this->css = $css;
			$this->len = strlen($css);
			$this->pos = 0;
			$this->stats = ['selectors' => 0, 'rewritten' => [], 'verbatim' => []];
			$this->in = [];
			return $this->rules(false);
		}

		/**
		 * Rules until EOF (top level) or the `}` closing the enclosing at-rule (left unconsumed).
		 */
		private function rules(bool $nested): string
		{
			$out = '';
			while ($this->pos < $this->len) {
				$c = $this->css[$this->pos];
				if ($c === '}') {
					if ($nested) {
						return $out;
					}
					$out .= $c; // Stray brace: browsers drop it, so do not interpret it either.
					$this->pos++;
					continue;
				}
				if (ctype_space($c)) {
					$out .= $c;
					$this->pos++;
					continue;
				}
				if ($c === '/' && ($this->css[$this->pos + 1] ?? '') === '*') {
					$out .= $this->comment();
					continue;
				}
				if ($c === '@') {
					$out .= $this->atRule();
					continue;
				}
				$prelude = $this->until('{');
				if ($this->pos >= $this->len) {
					$out .= $prelude; // Unterminated junk at the end: keep it as it was.
					break;
				}
				$out .= $this->selectorList($prelude) . $this->block();
			}
			return $out;
		}

		private function atRule(): string
		{
			$prelude = $this->until('{;');
			if ($this->pos >= $this->len) {
				return $prelude;
			}
			if ($this->css[$this->pos] === ';') { // @import, @charset, @namespace.
				$this->pos++;
				return $prelude . ';';
			}
			preg_match('/^@([-\w]+)/', $prelude, $m);
			$name = strtolower($m[1] ?? '');
			if (in_array($name, self::NESTING, true)) {
				$this->pos++; // {
				$inner = $this->rules(true);
				$this->pos++; // }
				return $prelude . '{' . $inner . '}';
			}
			// @keyframes (its 0%/from/to steps are not selectors), @font-face, @page, @property…
			$this->stats['verbatim'][] = trim($prelude);
			return $prelude . $this->block();
		}

		/**
		 * Text up to (not including) the first of $stops at bracket depth 0, skipping strings/comments.
		 */
		private function until(string $stops): string
		{
			$start = $this->pos;
			$depth = 0;
			while ($this->pos < $this->len) {
				$c = $this->css[$this->pos];
				if ($c === '"' || $c === "'") {
					$this->string();
					continue;
				}
				if ($c === '/' && ($this->css[$this->pos + 1] ?? '') === '*') {
					$this->comment();
					continue;
				}
				if ($c === '\\') {
					$this->pos += 2;
					continue;
				}
				if ($c === '(' || $c === '[') {
					$depth++;
				} elseif (($c === ')' || $c === ']') && $depth > 0) {
					$depth--;
				} elseif ($depth === 0 && strpos($stops, $c) !== false) {
					break;
				}
				$this->pos++;
			}
			return substr($this->css, $start, $this->pos - $start);
		}

		/**
		 * A `{…}` block including its braces, nested braces counted.
		 */
		private function block(): string
		{
			$start = $this->pos;
			$depth = 0;
			while ($this->pos < $this->len) {
				$c = $this->css[$this->pos];
				if ($c === '"' || $c === "'") {
					$this->string();
					continue;
				}
				if ($c === '/' && ($this->css[$this->pos + 1] ?? '') === '*') {
					$this->comment();
					continue;
				}
				if ($c === '\\') {
					$this->pos += 2;
					continue;
				}
				$this->pos++;
				if ($c === '{') {
					$depth++;
				} elseif ($c === '}' && --$depth === 0) {
					break;
				}
			}
			return substr($this->css, $start, $this->pos - $start);
		}

		private function string(): string
		{
			$start = $this->pos;
			$quote = $this->css[$this->pos++];
			while ($this->pos < $this->len) {
				$c = $this->css[$this->pos++];
				if ($c === '\\') {
					$this->pos++;
				} elseif ($c === $quote || $c === "\n") {
					break;
				}
			}
			return substr($this->css, $start, $this->pos - $start);
		}

		private function comment(): string
		{
			$end = strpos($this->css, '*/', $this->pos + 2);
			$end = $end === false ? $this->len : $end + 2;
			$text = substr($this->css, $this->pos, $end - $this->pos);
			$this->pos = $end;
			return $text;
		}

		/**
		 * Scope each selector of a comma list, keeping the author's whitespace around them.
		 */
		private function selectorList(string $prelude): string
		{
			$parts = [];
			$depth = 0;
			$start = 0;
			$n = strlen($prelude);
			for ($i = 0; $i < $n; $i++) {
				$c = $prelude[$i];
				if ($c === '"' || $c === "'") {
					for ($i++; $i < $n && $prelude[$i] !== $c; $i++) {
						if ($prelude[$i] === '\\') {
							$i++;
						}
					}
				} elseif ($c === '\\') {
					$i++;
				} elseif ($c === '(' || $c === '[') {
					$depth++;
				} elseif ($c === ')' || $c === ']') {
					$depth--;
				} elseif ($c === ',' && $depth === 0) {
					$parts[] = substr($prelude, $start, $i - $start);
					$start = $i + 1;
				}
			}
			$parts[] = substr($prelude, $start);

			foreach ($parts as &$part) {
				preg_match('/^(\s*)(.*?)(\s*)$/s', $part, $m);
				$part = $m[1] . $this->selector($m[2]) . $m[3];
			}
			return implode(',', $parts);
		}

		private function selector(string $sel): string
		{
			if ($sel === '') {
				return $sel;
			}
			$this->stats['selectors']++;
			$this->in[] = $sel;
			$scope = $this->scope;
			$quoted = preg_quote($scope, '/');

			if (preg_match('/^' . $quoted . '(?![-\w])/', $sel)) {
				return $sel; // Already scoped (second run).
			}

			// The document roots become the wrapper: `body` → scope, `html body > p` → `scope > p`,
			// `:root` → scope, `body.x` → `scope.x`.
			$rest = $sel;
			$root = false;
			while (preg_match('/^(?:html|body|:root)(?![-\w(])/i', $rest, $m)) {
				$root = true;
				$rest = substr($rest, strlen($m[0]));
				if (preg_match('/^\s*(?:>\s*)?(?=(?:html|body|:root)(?![-\w(]))/i', $rest, $c)) {
					$rest = substr($rest, strlen($c[0]));
				}
			}
			if ($root) {
				$new = $rest === '' || preg_match('/^[.#\[:]/', $rest) ? $scope . $rest : $scope . ' ' . ltrim($rest);
			} else {
				$new = $scope . ' ' . $sel;
			}
			if ($root || $sel === '*' || preg_match('/^[a-z]/i', $sel)) {
				$this->stats['rewritten'][$sel] = $new; // Worth showing: these were the global ones.
			}
			return $new;
		}
	}
}

/* ---------------------------------------------------------------- page helpers */

/**
 * The phone fixes and compatibility rules appended to the scoped sheet (already scoped).
 */
function ggt_legacy_fixes(): string
{
	return GGT_LEGACY_FIX_START . <<<'CSS'

  /* The theme's `html body` rule always outranked the legacy `body` rule, so these pages have shown
     body copy in the theme font, colour and line height. Keep that now the rule sits on the wrapper. */
  .ggt-legacy { font-family: inherit; color: inherit; line-height: inherit; }

  /* The contact line sits on the green CTA band; the theme link green would vanish there. */
  .ggt-legacy a[href^="mailto:"] { color: inherit; text-decoration: underline; text-underline-offset: 0.15em; }

  /* The wrapper is alignfull; its sections keep the content width and the block gap they had as
     direct children of the post content. */
  .ggt-legacy > * { max-width: var(--wp--style--global--content-size, 760px); margin-left: auto; margin-right: auto; }
  .ggt-legacy > * + * { margin-top: var(--wp--style--block-gap, 24px); }

  @media (max-width: 781px) {
    /* One inset instead of three: the content gutter (24px), each section's 5% and each card's
       24-40px left about 230px of text at 390px. Sections go full bleed and carry the only gutter. */
    .ggt-legacy { overflow-x: clip; }
    .ggt-legacy > * { max-width: none; }
    .ggt-legacy > * + * { margin-top: 0; }
    /* section[class] outranks the sheet's own `.ggt-legacy .need-section { padding: 70px 5% }`. */
    .ggt-legacy > section[class], .ggt-legacy > .breadcrumb { padding-left: clamp(16px, 4vw, 32px); padding-right: clamp(16px, 4vw, 32px); }
    .ggt-legacy > section[class] { padding-top: 56px; padding-bottom: 56px; }
    .ggt-legacy > section.hero { padding-top: 64px; padding-bottom: 56px; }
    .ggt-legacy > section.cta-section { padding-top: 72px; padding-bottom: 72px; }
    .ggt-legacy .need-card, .ggt-legacy .feature-card, .ggt-legacy .delivery-card, .ggt-legacy .other-card,
    .ggt-legacy .for-card, .ggt-legacy .for-callout, .ggt-legacy .urgency-stat, .ggt-legacy .chain-step,
    .ggt-legacy .compliance-highlight { padding: 20px clamp(16px, 4vw, 32px); }
    .ggt-legacy .trust-item { padding: 14px 16px; }
  }

  @media (max-width: 600px) {
    /* Icons and step numbers above the text rather than in a side column that eats its width. */
    .ggt-legacy .need-card, .ggt-legacy .urgency-stat, .ggt-legacy .compliance-item { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
    .ggt-legacy .process-step { grid-template-columns: 1fr; gap: 0.5rem; padding: 1.5rem 0; }
    .ggt-legacy .process-num { font-size: 2.25rem; }
    /* The two hero (and closing) buttons as a full-width stack. */
    .ggt-legacy .hero-actions, .ggt-legacy .btn-group { flex-direction: column; align-items: stretch; }
    .ggt-legacy .hero-actions .btn, .ggt-legacy .btn-group .btn { display: block; width: 100%; text-align: center; }
  }
CSS . "\n  " . GGT_LEGACY_FIX_END;
}

/**
 * Wrap the markup in the scope element, dropping document-level tags a page body ignores.
 *
 * @return array{0: string, 1: array<int, string>} New HTML and what was dropped.
 */
function ggt_legacy_wrap(string $html): array
{
	if (preg_match('/<div class="ggt-legacy alignfull"/', $html)) {
		return [$html, []];
	}
	if (preg_match('/<div class="ggt-legacy"/', $html)) { // Wrapped by the first version of this script.
		return [preg_replace('/<div class="ggt-legacy"/', '<div class="ggt-legacy alignfull"', $html, 1), ['(wrapper made alignfull)']];
	}
	$dropped = [];
	$html = preg_replace_callback(
		'#[ \t]*(?:<!DOCTYPE[^>]*>|</?html\b[^>]*>|</?head\b[^>]*>|</?body\b[^>]*>|<meta\s+charset=[^>]*>|<meta\s+name=["\']viewport["\'][^>]*>)[ \t]*\n?#i',
		static function ($m) use (&$dropped) {
			$dropped[] = trim($m[0]);
			return '';
		},
		$html
	);
	$html = preg_replace("/\n{3,}/", "\n\n", $html);
	return ["\n<div class=\"ggt-legacy alignfull\">\n" . trim($html, "\n") . "\n</div>\n", $dropped];
}

/**
 * Scope every <style> in the markup and (re)append the fixes block to the last one.
 *
 * @return array{0: string, 1: array<string, mixed>}
 */
function ggt_legacy_scope_styles(string $html): array
{
	$scoper = new GgtLegacyCssScoper(GGT_LEGACY_SCOPE);
	$report = ['selectors' => 0, 'rewritten' => [], 'verbatim' => [], 'fixes' => 'added', 'leaks' => []];
	$count = preg_match_all('#(<style\b[^>]*>)(.*?)(</style>)#is', $html);
	$index = 0;

	$html = preg_replace_callback('#(<style\b[^>]*>)(.*?)(</style>)#is', static function ($m) use ($scoper, &$report, $count, &$index) {
		$css = $m[2];
		$oldFixes = null;
		$start = strpos($css, GGT_LEGACY_FIX_START);
		if ($start !== false) {
			$end = strpos($css, GGT_LEGACY_FIX_END, $start);
			$end = $end === false ? strlen($css) : $end + strlen(GGT_LEGACY_FIX_END);
			$oldFixes = substr($css, $start, $end - $start);
			$css = rtrim(substr($css, 0, $start)) . substr($css, $end);
		}

		$scoped = $scoper->scope($css);
		$report['selectors'] += $scoper->stats['selectors'];
		$report['rewritten'] += $scoper->stats['rewritten'];
		$report['verbatim'] = array_merge($report['verbatim'], $scoper->stats['verbatim']);
		// Containment check: a fresh parse of the output must find only scoped selectors.
		if ($scoper->scope($scoped) !== $scoped) {
			$report['leaks'][] = '(second pass changed the sheet: scoping is not idempotent)';
		}
		foreach ($scoper->in as $sel) {
			if (!preg_match('/^' . preg_quote(GGT_LEGACY_SCOPE, '/') . '(?![-\w])/', $sel)) {
				$report['leaks'][] = $sel;
			}
		}

		if (++$index === $count) {
			$fixes = ggt_legacy_fixes();
			$scoper->scope($fixes);
			foreach ($scoper->in as $sel) {
				if (!preg_match('/^(?:div)?' . preg_quote(GGT_LEGACY_SCOPE, '/') . '(?![-\w])/', $sel)) {
					$report['leaks'][] = 'fixes: ' . $sel;
				}
			}
			$report['fixes'] = $oldFixes === null ? 'added' : ($oldFixes === $fixes ? 'unchanged' : 'updated');
			$scoped = rtrim($scoped) . "\n\n  " . $fixes . "\n";
		}
		return $m[1] . $scoped . $m[3];
	}, $html);

	return [$html, $report];
}

/**
 * Restore the contact line's domain and link plain-text email addresses (visible text only).
 *
 * @return array{0: string, 1: array<int, string>}
 */
function ggt_legacy_contact(string $html): array
{
	$notes = [];
	$hosts = array_unique(array_filter([
		'going-green-transport.lndo.site',
		(string) wp_parse_url(home_url(), PHP_URL_HOST),
	], static fn ($h) => $h !== '' && !preg_match('/^(www\.)?' . preg_quote(GGT_LEGACY_DOMAIN, '/') . '$/i', $h)));
	$hostRe = '(?:' . implode('|', array_map(static fn ($h) => preg_quote($h, '/'), $hosts)) . ')';

	$chunks = preg_split('#(<style\b.*?</style>|<script\b.*?</script>|<!--.*?-->|<[^>]*>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
	$inLink = false;
	foreach ($chunks as $i => &$chunk) {
		if ($i % 2) { // A tag, comment, style or script.
			if (preg_match('#^<a\b#i', $chunk)) {
				$inLink = true;
			} elseif (preg_match('#^</a\s*>#i', $chunk)) {
				$inLink = false;
			}
			continue;
		}
		if ($hosts) {
			$chunk = preg_replace_callback('/(?<![\w.-])([\w.+-]+@)?' . $hostRe . '(?![\w-])/i', static function ($m) use (&$notes) {
				$new = ($m[1] ?? '') . GGT_LEGACY_DOMAIN;
				$notes[] = "text \"{$m[0]}\" → \"{$new}\" (search-replace artefact)";
				return $new;
			}, $chunk);
		}
		if (!$inLink) {
			$chunk = preg_replace_callback('/(?<![\w.@:\/-])[\w.+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+(?![\w-])/i', static function ($m) use (&$notes) {
				$notes[] = "linked {$m[0]} (mailto:)";
				return '<a href="mailto:' . esc_attr($m[0]) . '">' . $m[0] . '</a>';
			}, $chunk);
		}
	}
	unset($chunk);
	return [implode('', $chunks), $notes];
}

/* ---------------------------------------------------------------- run */

$ggtTargets = isset($ggtOpts['page']) ? [(string) $ggtOpts['page']] : GGT_LEGACY_PAGES;
$ggtApply = isset($ggtOpts['apply']);
$ggtFailed = false;

foreach ($ggtTargets as $ggtTarget) {
	$failed = false;
	$ggtPost = ctype_digit($ggtTarget) ? get_post((int) $ggtTarget) : get_page_by_path($ggtTarget, OBJECT, 'page');
	if (!$ggtPost) {
		WP_CLI::warning("{$ggtTarget}\tnot found");
		continue;
	}
	$label = "{$ggtPost->ID} /{$ggtPost->post_name}/";
	$old = $ggtPost->post_content;
	$blocks = parse_blocks($old);
	$notes = [];
	$touched = 0;

	foreach ($blocks as &$block) {
		if ($block['blockName'] !== 'core/html' || stripos($block['innerHTML'], '<style') === false) {
			continue;
		}
		$touched++;
		$html = $block['innerHTML'];
		if (preg_match('/going-green-transport\.lndo\.site/i', $html) && preg_match('/[\w.+-]+@going-green-transport\.lndo\.site/i', $html)) {
			$notes[] = 'NOTE stored content has an email on going-green-transport.lndo.site: a local search-replace rewrote it';
		}

		[$html, $dropped] = ggt_legacy_wrap($html);
		$notes[] = match (true) {
			!$dropped => 'already wrapped',
			$dropped === ['(wrapper made alignfull)'] => 'wrapper made alignfull',
			default => 'wrapped in div.ggt-legacy.alignfull; dropped ' . implode(' ', $dropped),
		};

		[$html, $report] = ggt_legacy_scope_styles($html);
		$notes[] = sprintf('css: %d selectors under %s; fixes block %s', $report['selectors'], GGT_LEGACY_SCOPE, $report['fixes']);
		foreach ($report['rewritten'] as $from => $to) {
			$notes[] = "  {$from}  →  {$to}";
		}
		foreach (array_unique($report['verbatim']) as $at) {
			$notes[] = "  kept as is: {$at}";
		}
		if ($report['leaks']) {
			$failed = $ggtFailed = true;
			$notes[] = 'ERROR selectors not under the scope: ' . implode(' | ', $report['leaks']);
		}

		[$html, $contact] = ggt_legacy_contact($html);
		array_push($notes, ...$contact);

		$block['innerHTML'] = $html;
		$block['innerContent'] = [$html];
	}
	unset($block);

	if (!$touched) {
		WP_CLI::log("{$label}\tno legacy core/html block with a <style>; skipped");
		continue;
	}
	$new = serialize_blocks($blocks);
	WP_CLI::log("{$label}\n  " . implode("\n  ", $notes));

	if ($new === $old) {
		WP_CLI::log("  unchanged\n");
		continue;
	}
	WP_CLI::log(sprintf('  content %d → %d bytes', strlen($old), strlen($new)));
	if (isset($ggtOpts['diff'])) {
		$a = wp_tempnam('a');
		$b = wp_tempnam('b');
		file_put_contents($a, $old);
		file_put_contents($b, $new);
		WP_CLI::log(shell_exec('diff -u ' . escapeshellarg($a) . ' ' . escapeshellarg($b) . " --label before --label after"));
		unlink($a);
		unlink($b);
	}
	if (!$ggtApply || $failed) {
		WP_CLI::log($failed ? "  not saved (errors above)\n" : "  (dry run: add `apply` to save)\n");
		continue;
	}

	// Snapshot the current content as a revision first (no-op if the latest revision already matches),
	// so there is always a "before" to restore.
	wp_save_post_revision($ggtPost->ID);
	$before = (int) array_key_first(wp_get_post_revisions($ggtPost->ID, ['numberposts' => 1]) ?: [0 => 0]);

	kses_remove_filters(); // The markup carries <style> and <link>; WP-CLI has no unfiltered_html user.
	// The pages still carry the old site's `global-header-and-footer` template slug, which this theme
	// does not register; wp_update_post() would reject it. An empty value leaves the meta untouched.
	$saved = wp_update_post(['ID' => $ggtPost->ID, 'post_content' => wp_slash($new), 'page_template' => ''], true);
	if (is_wp_error($saved)) {
		WP_CLI::warning("{$label}\tERROR " . $saved->get_error_message());
		continue;
	}
	clean_post_cache($ggtPost->ID);
	$stored = get_post($ggtPost->ID)->post_content;
	$after = (int) array_key_first(wp_get_post_revisions($ggtPost->ID, ['numberposts' => 1]) ?: [0 => 0]);
	// Restoring a revision goes through wp_update_post() too, which resets an unregistered template
	// slug to "default"; the rollback line puts it back.
	$template = (string) get_post_meta($ggtPost->ID, '_wp_page_template', true);
	WP_CLI::log(sprintf(
		"  saved%s; revision before %d, after %d\n  roll back: wp eval 'kses_remove_filters(); wp_restore_post_revision(%d); update_post_meta(%d, \"_wp_page_template\", \"%s\");'\n",
		$stored === $new ? '' : ' BUT the stored content differs from what was written',
		$before,
		$after,
		$before,
		$ggtPost->ID,
		$template
	));
}

if ($ggtFailed) {
	WP_CLI::error('Some selectors could not be scoped; nothing was saved for those pages.');
}

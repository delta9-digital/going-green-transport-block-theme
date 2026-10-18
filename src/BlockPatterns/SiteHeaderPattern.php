<?php

/**
 * Pattern: site header (comp `Site Nav`).
 *
 * Core blocks only, pulled into parts/header.html with a pattern block so the logo uses the theme
 * URL. Sticky bar, blur, active underline, Pricing panel and the mobile drawer are in
 * assets/styles/parts/theme/_header.scss.
 *
 * @package GoingGreenTransportTheme\BlockPatterns
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\BlockPatterns;

use GoingGreenTransportThemeVendor\EightshiftLibs\BlockPatterns\AbstractBlockPattern;

/**
 * Class SiteHeaderPattern
 */
class SiteHeaderPattern extends AbstractBlockPattern
{
	/**
	 * Get the pattern categories.
	 *
	 * @return array<string>
	 */
	protected function getCategories(): array
	{
		return [BlockPatternCategories::CATEGORY];
	}

	/**
	 * Get the pattern keywords.
	 *
	 * @return array<string>
	 */
	protected function getKeywords(): array
	{
		return ['header', 'navigation', 'menu'];
	}

	/**
	 * Get the pattern name with namespace.
	 *
	 * @return string
	 */
	protected function getName(): string
	{
		return 'going-green/site-header';
	}

	/**
	 * Get the pattern title.
	 *
	 * @return string
	 */
	protected function getTitle(): string
	{
		return \esc_html__('Site header', 'going-green-transport');
	}

	/**
	 * Get the pattern description.
	 *
	 * @return string
	 */
	protected function getDescription(): string
	{
		return \esc_html__('Sticky header: wordmark, main navigation with the Pricing panel, Get a Quote button.', 'going-green-transport');
	}

	/**
	 * Serialized navigation link.
	 *
	 * @param string $label Label.
	 * @param string $key SiteLinks key.
	 * @param string $description Optional description (shown in the Pricing panel).
	 * @param string $class Optional class name.
	 *
	 * @return string
	 */
	private function link(string $label, string $key, string $description = '', string $class = ''): string
	{
		$attrs = ['label' => $label, 'url' => SiteLinks::url($key), 'kind' => 'custom', 'isTopLevelLink' => $description === ''];

		if ($description) {
			$attrs['description'] = $description;
		}

		if ($class) {
			$attrs['className'] = $class;
		}

		return '<!-- wp:navigation-link ' . \wp_json_encode($attrs, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . " /-->\n";
	}

	/**
	 * Get the pattern content.
	 *
	 * @return string
	 */
	protected function getContent(): string
	{
		$pricing = '<!-- wp:navigation-submenu ' . \wp_json_encode(['label' => \__('Pricing', 'going-green-transport'), 'kind' => 'custom', 'isTopLevelItem' => true]) . " -->\n"
			. $this->link(\__('Flat-Rate', 'going-green-transport'), 'flat-rate', \__('Regional routes on set days · $150 / $250', 'going-green-transport'))
			. $this->link(\__('On-Demand', 'going-green-transport'), 'on-demand', \__('Any day, direct run · priced per request', 'going-green-transport'))
			. "<!-- /wp:navigation-submenu -->\n";

		$items = $this->link(\__('Services', 'going-green-transport'), 'services')
			. $this->link(\__('Locations', 'going-green-transport'), 'locations')
			. $pricing
			. $this->link(\__('How It Works', 'going-green-transport'), 'how-it-works')
			. $this->link(\__('About Us', 'going-green-transport'), 'about')
			. $this->link(\__('Resources', 'going-green-transport'), 'resources');

		$nav = '<!-- wp:navigation ' . \wp_json_encode([
			'overlayMenu' => 'mobile',
			'openSubmenusOnClick' => false,
			'overlayBackgroundColor' => 'gray-50',
			'overlayTextColor' => 'green-900',
			'className' => 'ggt-hdr__menu',
			'ariaLabel' => \__('Main', 'going-green-transport'),
			'layout' => ['type' => 'flex', 'flexWrap' => 'nowrap', 'justifyContent' => 'right'],
		], \JSON_UNESCAPED_SLASHES) . " -->\n{$items}<!-- /wp:navigation -->\n";

		$logoSrc = \esc_url(\get_theme_file_uri('assets/images/brand/ggt-wordmark-box.svg'));
		$logoAlt = \esc_attr__('Going Green Transport', 'going-green-transport');
		$home = \esc_url(SiteLinks::url('home'));
		$logo = '<!-- wp:image ' . \wp_json_encode(['sizeSlug' => 'full', 'linkDestination' => 'custom', 'className' => 'ggt-hdr__logo'], \JSON_UNESCAPED_SLASHES) . " -->\n"
			. "<figure class=\"wp-block-image size-full ggt-hdr__logo\"><a href=\"{$home}\"><img src=\"{$logoSrc}\" alt=\"{$logoAlt}\" width=\"94\" height=\"48\"/></a></figure>\n"
			. "<!-- /wp:image -->\n";

		$quote = \esc_url(SiteLinks::url('quote'));
		$ctaText = \esc_html__('Get a Quote', 'going-green-transport');
		$cta = "<!-- wp:buttons {\"className\":\"ggt-hdr__cta\"} -->\n<div class=\"wp-block-buttons ggt-hdr__cta\"><!-- wp:button -->\n"
			. "<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"{$quote}\">{$ctaText}</a></div>\n"
			. "<!-- /wp:button --></div>\n<!-- /wp:buttons -->\n";

		return "<!-- wp:group {\"anchor\":\"top\",\"align\":\"full\",\"className\":\"ggt-hdr\",\"layout\":{\"type\":\"constrained\"}} -->\n<div id=\"top\" class=\"wp-block-group alignfull ggt-hdr\">"
			. "<!-- wp:group {\"align\":\"wide\",\"className\":\"ggt-hdr__bar\",\"layout\":{\"type\":\"flex\",\"flexWrap\":\"nowrap\",\"justifyContent\":\"space-between\"}} -->\n<div class=\"wp-block-group alignwide ggt-hdr__bar\">"
			. $logo
			. "<!-- wp:group {\"className\":\"ggt-hdr__right\",\"layout\":{\"type\":\"flex\",\"flexWrap\":\"nowrap\",\"justifyContent\":\"right\"}} -->\n<div class=\"wp-block-group ggt-hdr__right\">"
			. $nav
			. $cta
			. "</div>\n<!-- /wp:group -->\n"
			. "</div>\n<!-- /wp:group -->\n"
			. "</div>\n<!-- /wp:group -->";
	}
}

<?php

/**
 * Pattern: site footer (comp `Site Footer`): lime band with the sign-up form (src/Signup/SignupForm.php)
 * beside call and email links, then logo, three link columns, a phone/email row and the legal row.
 *
 * Core blocks only, pulled into parts/footer.html with a pattern block so the logo uses the theme
 * URL and the year stays current. Styles: assets/styles/parts/theme/_footer.scss.
 *
 * @package GoingGreenTransportTheme\BlockPatterns
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\BlockPatterns;

use GoingGreenTransportTheme\Signup\SignupForm;
use GoingGreenTransportThemeVendor\EightshiftLibs\BlockPatterns\AbstractBlockPattern;

/**
 * Class SiteFooterPattern
 */
class SiteFooterPattern extends AbstractBlockPattern
{
	/**
	 * Link columns: heading => [label => SiteLinks key].
	 *
	 * @var array<string, array<string, string>>
	 */
	private const COLUMNS = [
		'Company' => [
			'About Us' => 'about',
			'Contact & Get a Quote' => 'quote',
			'FAQ' => 'faq',
			'Locations' => 'locations',
			'Privacy Policy' => 'privacy',
			'Terms & Conditions' => 'terms',
		],
		'Services' => [
			'All Services' => 'services',
			'Cultivation & Production' => 'services-cultivation',
			'Lab Sample Courier' => 'services-lab',
			'Dispensary Delivery' => 'services-dispensary',
			'Flat-Rate Transport' => 'flat-rate',
			'On-Demand Transport' => 'on-demand',
		],
		'Guide' => [
			'Cannabis Cultivator' => 'guide-cultivator',
			'Cannabis Testing' => 'guide-testing',
			'Cannabis Manufacturer' => 'guide-manufacturer',
		],
	];

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
		return ['footer', 'contact', 'links'];
	}

	/**
	 * Get the pattern name with namespace.
	 *
	 * @return string
	 */
	protected function getName(): string
	{
		return 'going-green/site-footer';
	}

	/**
	 * Get the pattern title.
	 *
	 * @return string
	 */
	protected function getTitle(): string
	{
		return \esc_html__('Site footer', 'going-green-transport');
	}

	/**
	 * Get the pattern description.
	 *
	 * @return string
	 */
	protected function getDescription(): string
	{
		return \esc_html__('Lime sign-up band, logo and contacts, Company / Services / Guide links, legal row.', 'going-green-transport');
	}

	/**
	 * Serialized paragraph.
	 *
	 * @param string $html Inner HTML (already escaped).
	 * @param string $class Class name.
	 *
	 * @return string
	 */
	private function paragraph(string $html, string $class): string
	{
		return '<!-- wp:paragraph {"className":"' . $class . "\"} -->\n<p class=\"{$class}\">{$html}</p>\n<!-- /wp:paragraph -->\n";
	}

	/**
	 * Serialized group.
	 *
	 * @param string $inner Inner blocks.
	 * @param array<string, mixed> $attrs Block attributes (className required).
	 *
	 * @return string
	 */
	private function group(string $inner, array $attrs): string
	{
		$classes = \trim('wp-block-group ' . (isset($attrs['align']) ? "align{$attrs['align']} " : '') . $attrs['className']);

		return '<!-- wp:group ' . \wp_json_encode($attrs, \JSON_UNESCAPED_SLASHES) . " -->\n<div class=\"{$classes}\">{$inner}</div>\n<!-- /wp:group -->\n";
	}

	/**
	 * Escaped anchor.
	 *
	 * @param string $label Label.
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function a(string $label, string $url): string
	{
		return '<a href="' . \esc_url($url) . '">' . \esc_html($label) . '</a>';
	}

	/**
	 * One link column: eyebrow heading + vertical navigation.
	 *
	 * @param string $heading Column heading.
	 * @param array<string, string> $links Label => SiteLinks key.
	 *
	 * @return string
	 */
	private function column(string $heading, array $links): string
	{
		$items = '';
		foreach ($links as $label => $key) {
			$items .= '<!-- wp:navigation-link ' . \wp_json_encode(['label' => \__($label, 'going-green-transport'), 'url' => SiteLinks::url($key), 'kind' => 'custom'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . " /-->\n"; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		}

		$title = \esc_html__($heading, 'going-green-transport'); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		$nav = '<!-- wp:navigation ' . \wp_json_encode([
			'overlayMenu' => 'never',
			'className' => 'ggt-ftr__links',
			'ariaLabel' => $heading,
			'layout' => ['type' => 'flex', 'orientation' => 'vertical'],
		], \JSON_UNESCAPED_SLASHES) . " -->\n{$items}<!-- /wp:navigation -->\n";

		return $this->group(
			"<!-- wp:heading {\"level\":4,\"className\":\"ggt-ftr__heading\"} -->\n<h4 class=\"wp-block-heading ggt-ftr__heading\">{$title}</h4>\n<!-- /wp:heading -->\n" . $nav,
			['className' => 'ggt-ftr__col', 'layout' => ['type' => 'default']]
		);
	}

	/**
	 * Get the pattern content.
	 *
	 * @return string
	 */
	protected function getContent(): string
	{
		$email = SiteLinks::EMAIL;
		$mailto = 'mailto:' . $email;

		$intro = "<!-- wp:heading {\"level\":2,\"className\":\"ggt-ftr__band-title\"} -->\n<h2 class=\"wp-block-heading ggt-ftr__band-title\">"
			. \esc_html__('Stay in the Loop.', 'going-green-transport') . "</h2>\n<!-- /wp:heading -->\n"
			. $this->paragraph(\esc_html__('Route updates, compliance news and new zones for licensed Minnesota operators.', 'going-green-transport'), 'ggt-ftr__band-text');

		$form = "<!-- wp:html -->\n" . SignupForm::markup() . "\n<!-- /wp:html -->\n";

		// Right column: call and email, icon plate left, label and value right.
		$contact = fn(string $icon, string $label, string $value, string $href): string =>
			'<a class="ggt-ftr__contact" href="' . \esc_url($href) . '"><span class="ggt-ftr__contact-icon"><img src="'
			. \esc_url(\get_theme_file_uri("assets/images/icons/{$icon}")) . '" alt="" width="24" height="24"></span>'
			. '<span class="ggt-ftr__contact-text"><span class="ggt-ftr__contact-label">' . \esc_html($label) . '</span>'
			. '<span class="ggt-ftr__contact-value">' . \esc_html($value) . '</span></span></a>';
		$contacts = "<!-- wp:html -->\n<div class=\"ggt-ftr__contact-list\">"
			. $contact('phone-lime.svg', \__('Call us', 'going-green-transport'), SiteLinks::PHONE, 'tel:' . \str_replace('-', '', SiteLinks::PHONE))
			. $contact('mail-lime.svg', \__('Email us', 'going-green-transport'), $email, $mailto)
			. "</div>\n<!-- /wp:html -->\n";

		$band = $this->group(
			$this->group(
				$this->group(
					$this->group($intro, ['className' => 'ggt-ftr__band-intro', 'layout' => ['type' => 'default']]) . $form,
					['className' => 'ggt-ftr__band-form', 'layout' => ['type' => 'default']]
				)
				. $contacts,
				['align' => 'wide', 'className' => 'ggt-ftr__band-inner', 'layout' => ['type' => 'default']]
			),
			['align' => 'full', 'className' => 'ggt-ftr__band', 'backgroundColor' => 'lime-500', 'layout' => ['type' => 'constrained']]
		);
		// The serializer above does not add the background classes; add them for a valid block.
		$band = \str_replace('class="wp-block-group alignfull ggt-ftr__band"', 'class="wp-block-group alignfull ggt-ftr__band has-lime-500-background-color has-background"', $band);

		$logoSrc = \esc_url(\get_theme_file_uri('assets/images/brand/ggt-logo-cutout-green.svg'));
		$logoAlt = \esc_attr__('Going Green Transport LLC', 'going-green-transport');
		$logo = "<!-- wp:image {\"sizeSlug\":\"full\",\"className\":\"ggt-ftr__logo\"} -->\n<figure class=\"wp-block-image size-full ggt-ftr__logo\"><img src=\"{$logoSrc}\" alt=\"{$logoAlt}\" width=\"432\" height=\"216\"/></figure>\n<!-- /wp:image -->\n";

		$brand = $this->group(
			$logo . $this->paragraph(\esc_html__('"From seed to sale, we never fail."', 'going-green-transport'), 'ggt-ftr__quote'),
			['className' => 'ggt-ftr__col ggt-ftr__brand', 'layout' => ['type' => 'default']]
		);

		$columns = $brand;
		foreach (self::COLUMNS as $heading => $links) {
			$columns .= $this->column($heading, $links);
		}

		$grid = $this->group($columns, ['align' => 'wide', 'className' => 'ggt-ftr__grid', 'layout' => ['type' => 'grid', 'minimumColumnWidth' => '200px']]);

		// Phone and email in one row along the bottom, above the legal line.
		$reach = $this->group(
			$this->paragraph(
				$this->a(SiteLinks::PHONE, 'tel:' . \str_replace('-', '', SiteLinks::PHONE))
				. $this->a($email, $mailto),
				'ggt-ftr__reach-links'
			),
			['align' => 'wide', 'className' => 'ggt-ftr__reach', 'layout' => ['type' => 'default']]
		);

		$year = \gmdate('Y');
		$legal = $this->group(
			$this->paragraph(
				\esc_html(\sprintf(
					/* translators: %s: year. */
					\__('© %s Going Green Transport LLC, All Rights Reserved. Minnesota Licensed Cannabis Transporter. Woman-Owned & Female-Founded.', 'going-green-transport'),
					$year
				)),
				'ggt-ftr__copy'
			)
			. $this->paragraph(
				$this->a(\__('Privacy', 'going-green-transport'), SiteLinks::url('privacy'))
				. $this->a(\__('Terms', 'going-green-transport'), SiteLinks::url('terms'))
				. '<a class="ggt-ftr__top" href="#top">' . \esc_html__('Back to Top ↑', 'going-green-transport') . '</a>',
				'ggt-ftr__legal-links'
			),
			['align' => 'wide', 'className' => 'ggt-ftr__legal', 'layout' => ['type' => 'flex', 'flexWrap' => 'wrap', 'justifyContent' => 'space-between']]
		);

		// Decorative wordmark drawn large and faint behind the footer.
		$wordmark = "<!-- wp:html -->\n<img class=\"ggt-ftr__wordmark\" src=\"" . \esc_url(\get_theme_file_uri('assets/images/brand/ggt-wordmark-box.svg'))
			. "\" alt=\"\" aria-hidden=\"true\" width=\"843\" height=\"431\" loading=\"lazy\" decoding=\"async\">\n<!-- /wp:html -->\n";

		$main = $this->group($wordmark . $grid . $reach . $legal, ['align' => 'full', 'className' => 'ggt-ftr__main', 'layout' => ['type' => 'constrained']]);

		return $this->group($band . $main, ['align' => 'full', 'className' => 'ggt-ftr', 'layout' => ['type' => 'constrained']]);
	}
}

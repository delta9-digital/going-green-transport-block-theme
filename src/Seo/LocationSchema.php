<?php

/**
 * Additions to Yoast's schema graph:
 * - every page: business facts on Yoast's `#organization` node (service area, hours, contact point,
 *   slogan, topics, the service catalogue). Name, logo and sameAs come from Yoast > Settings > Site
 *   representation; its phone, email, legal name, description and founding date are added here
 *   (Yoast Free stores them without printing them).
 * - city pages: a Service piece (licensed cannabis transport) with the city as areaServed (plus
 *   nearby towns), provided by that Organization.
 * - pages with core/accordion items (Home, FAQ, Get a Quote): the WebPage becomes an FAQPage with one
 *   Question per accordion item, the way Yoast's own FAQ block does it.
 *
 * @package GoingGreenTransportTheme\Seo
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Seo;

use GoingGreenTransportTheme\BlockPatterns\SiteLinks;
use GoingGreenTransportTheme\CustomMeta\AbstractPostMeta;
use GoingGreenTransportTheme\CustomPostType\LocationPostType;
use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * Class LocationSchema
 */
class LocationSchema implements ServiceInterface
{
	/**
	 * Office hours (live site's LocalBusiness: Mo-Fr 08:00-18:00).
	 */
	private const HOURS = [
		'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
		'opens' => '08:00',
		'closes' => '18:00',
	];

	/**
	 * Services for the offer catalogue: page path => [name, description].
	 */
	private const SERVICES = [
		'/cultivation-production-in-minnesota/' => [
			'Cultivation & Production Transport',
			'Same-day cannabis transport from grow operations and processing facilities to manufacturers, wholesalers and dispensaries across Minnesota.',
		],
		'/lab-sample-transport-in-minnesota/' => [
			'Lab Sample Courier Transport',
			'Rapid, compliant cannabis sample transport between licensed facilities and testing labs in Minnesota with full chain-of-custody documentation.',
		],
		'/dispensary-transportation-in-minnesota/' => [
			'Dispensary Delivery Transport',
			'Secure, same-day cannabis delivery from producers and manufacturers directly to Minnesota retail dispensaries.',
		],
	];

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('wpseo_schema_graph', [$this, 'addService'], 10, 2);
		\add_filter('wpseo_schema_graph', [$this, 'enrichOrganization'], 11, 2);
		\add_filter('wpseo_schema_graph', [$this, 'addFaq'], 12, 2);
	}

	/**
	 * Append the Service piece on a location.
	 *
	 * @param array<int, array<string, mixed>> $graph Schema graph.
	 * @param object $context Yoast Meta_Tags_Context (site_url, canonical).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addService(array $graph, $context): array
	{
		if (!\is_singular(LocationPostType::POST_TYPE_SLUG)) {
			return $graph;
		}

		$postId = (int) \get_queried_object_id();
		$meta = static fn(string $key) => \get_post_meta($postId, AbstractPostMeta::PREFIX . $key, true);
		$city = (string) ($meta('city') ?: \get_the_title($postId));
		$canonical = (string) ($context->canonical ?? \get_permalink($postId));

		$area = ['@type' => 'City', 'name' => "{$city}, MN"];

		if ($meta('lat') !== '' && $meta('lng') !== '') {
			$area['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $meta('lat'), 'longitude' => (float) $meta('lng')];
		}

		$nearby = \array_map(
			static fn(string $place) => ['@type' => 'Place', 'name' => \str_contains($place, ',') ? $place : "{$place}, MN"],
			\array_filter(\array_map('trim', (array) $meta('nearby')))
		);

		$graph[] = [
			'@type' => 'Service',
			'@id' => "{$canonical}#service",
			'name' => \sprintf(\__('Licensed cannabis transport in %s, MN', 'going-green-transport'), $city),
			'serviceType' => \__('Licensed cannabis transport', 'going-green-transport'),
			'provider' => ['@id' => $this->organizationId($context)],
			'areaServed' => \array_values(\array_merge([$area], $nearby)),
			'url' => $canonical,
		];

		return $graph;
	}

	/**
	 * Add the business facts Yoast has no setting for to its Organization node.
	 *
	 * A service-area business with no public street address, so it stays an Organization with
	 * areaServed rather than a LocalBusiness.
	 *
	 * @param array<int, array<string, mixed>> $graph Schema graph.
	 * @param object $context Yoast Meta_Tags_Context.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function enrichOrganization(array $graph, $context): array
	{
		$orgId = $this->organizationId($context);

		foreach ($graph as $i => $node) {
			if (($node['@id'] ?? '') !== $orgId) {
				continue;
			}

			$phone = $this->yoast('org-phone') ?: '+1-' . SiteLinks::PHONE;
			$email = $this->yoast('org-email') ?: SiteLinks::EMAIL;

			// Yoast Free saves these Site representation fields but only Premium prints them.
			$graph[$i] += \array_filter([
				'legalName' => $this->yoast('org-legal-name'),
				'description' => $this->yoast('org-description'),
				'foundingDate' => $this->yoast('org-founding-date'),
				'numberOfEmployees' => $this->yoast('org-number-employees'),
			]);

			$graph[$i] += [
				'alternateName' => 'Going Green Transport',
				'slogan' => 'From Seed to Sale, We Never Fail',
				'telephone' => $phone,
				'email' => $email,
				'areaServed' => ['@type' => 'State', 'name' => 'Minnesota', 'sameAs' => 'https://en.wikipedia.org/wiki/Minnesota'],
				'knowsAbout' => ['Cannabis transportation', 'Cannabis logistics', 'Metrc compliance', 'Chain of custody', 'Cannabis supply chain'],
				'contactPoint' => [
					'@type' => 'ContactPoint',
					'contactType' => 'customer service',
					'telephone' => $phone,
					'email' => $email,
					'areaServed' => 'US-MN',
					'availableLanguage' => 'English',
					'hoursAvailable' => $this->hours(),
				],
				'openingHoursSpecification' => $this->hours(),
				'hasOfferCatalog' => $this->catalogue(),
			];
		}

		return $graph;
	}

	/**
	 * Turn the WebPage into an FAQPage when the page has accordion items.
	 *
	 * @param array<int, array<string, mixed>> $graph Schema graph.
	 * @param object $context Yoast Meta_Tags_Context (canonical, main_schema_id).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addFaq(array $graph, $context): array
	{
		if (!\is_singular('page')) {
			return $graph;
		}

		$pairs = $this->faqPairs(\parse_blocks((string) \get_post_field('post_content', (int) \get_queried_object_id())));

		if (\count($pairs) < 2) {
			return $graph;
		}

		$pageId = (string) ($context->main_schema_id ?? $context->canonical ?? '');

		foreach ($graph as $i => $node) {
			if (($node['@id'] ?? '') !== $pageId) {
				continue;
			}

			$types = \array_values(\array_unique(\array_merge((array) ($node['@type'] ?? 'WebPage'), ['FAQPage'])));
			$graph[$i]['@type'] = $types;
			$graph[$i]['mainEntity'] = [];

			foreach ($pairs as $n => [$question, $answer]) {
				$id = "{$pageId}#faq-question-" . ($n + 1);
				$graph[$i]['mainEntity'][] = ['@id' => $id];
				$graph[] = [
					'@type' => 'Question',
					'@id' => $id,
					'position' => $n + 1,
					'url' => $id,
					'name' => $question,
					'answerCount' => 1,
					'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
					'inLanguage' => \get_bloginfo('language'),
				];
			}

			break;
		}

		return $graph;
	}

	/**
	 * Question/answer text of every core/accordion-item in a block tree, in order.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	private function faqPairs(array $blocks): array
	{
		$pairs = [];

		foreach ($blocks as $block) {
			if (($block['blockName'] ?? '') !== 'core/accordion-item') {
				\array_push($pairs, ...$this->faqPairs($block['innerBlocks'] ?? []));
				continue;
			}

			$question = '';
			$answer = '';

			foreach ($block['innerBlocks'] ?? [] as $part) {
				if ($part['blockName'] === 'core/accordion-heading') {
					$question = $this->plain((string) $part['innerHTML']);
					// "3. Do you ...?" on the FAQ page: the number is layout, not part of the question.
					$question = (string) \preg_replace('/^\d+[.)]\s*/', '', $question);
				} elseif ($part['blockName'] === 'core/accordion-panel') {
					$answer = $this->answerHtml($part['innerBlocks'] ?? []);
				}
			}

			if ($question !== '' && $answer !== '') {
				$pairs[] = [$question, $answer];
			}
		}

		return $pairs;
	}

	/**
	 * Answer markup limited to the tags Google reads in FAQ answers.
	 *
	 * @param array<int, array<string, mixed>> $blocks Panel inner blocks.
	 *
	 * @return string
	 */
	private function answerHtml(array $blocks): string
	{
		$html = \implode('', \array_map('render_block', $blocks));
		$html = \wp_kses($html, [
			'p' => [],
			'br' => [],
			'ul' => [],
			'ol' => [],
			'li' => [],
			'a' => ['href' => true],
			'strong' => [],
			'b' => [],
			'em' => [],
			'i' => [],
		]);

		$html = (string) \preg_replace('#<br\s*/?>\s*(</(?:p|li)>)#', '$1', $html);

		return \trim((string) \preg_replace('/\s+/', ' ', $html));
	}

	/**
	 * Visible text of a block's markup, without aria-hidden decoration.
	 *
	 * @param string $html Markup.
	 *
	 * @return string
	 */
	private function plain(string $html): string
	{
		// Drop the toggle's "+" icon (aria-hidden) before taking the text.
		$html = (string) \preg_replace('#<span[^>]*aria-hidden="true"[^>]*>.*?</span>#s', '', $html);
		$text = \html_entity_decode(\wp_strip_all_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

		return \trim((string) \preg_replace('/\s+/u', ' ', $text));
	}

	/**
	 * Opening hours as OpeningHoursSpecification.
	 *
	 * @return array<string, mixed>
	 */
	private function hours(): array
	{
		return [
			'@type' => 'OpeningHoursSpecification',
			'dayOfWeek' => self::HOURS['days'],
			'opens' => self::HOURS['opens'],
			'closes' => self::HOURS['closes'],
		];
	}

	/**
	 * The three services as an OfferCatalog.
	 *
	 * @return array<string, mixed>
	 */
	private function catalogue(): array
	{
		$items = [];

		foreach (self::SERVICES as $path => [$name, $description]) {
			$items[] = [
				'@type' => 'Offer',
				'itemOffered' => [
					'@type' => 'Service',
					'name' => $name,
					'description' => $description,
					'url' => \home_url($path),
					'areaServed' => ['@type' => 'State', 'name' => 'Minnesota'],
				],
			];
		}

		return [
			'@type' => 'OfferCatalog',
			'name' => 'Cannabis Transport Services',
			'itemListElement' => $items,
		];
	}

	/**
	 * A Yoast setting as a string ('' when Yoast is off or the field is empty).
	 *
	 * @param string $key Option key in wpseo_titles.
	 *
	 * @return string
	 */
	private function yoast(string $key): string
	{
		return \class_exists('WPSEO_Options') ? \trim((string) \WPSEO_Options::get($key, '')) : '';
	}

	/**
	 * Yoast's Organization @id.
	 *
	 * @param object $context Yoast Meta_Tags_Context.
	 *
	 * @return string
	 */
	private function organizationId($context): string
	{
		return \trailingslashit((string) ($context->site_url ?? \home_url('/'))) . '#organization';
	}
}

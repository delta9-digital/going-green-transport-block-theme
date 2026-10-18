<?php

/**
 * Site URLs used by the header and footer patterns, in one place.
 *
 * Paths point at the pages that exist today. Flat-Rate, On-Demand and How It Works are the
 * proposed slugs for pages still to be built (BUILD-PLAN §2); the locations hub moves to
 * /locations/ with a 301 in the locations round.
 *
 * @package GoingGreenTransportTheme\BlockPatterns
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\BlockPatterns;

/**
 * Class SiteLinks
 */
final class SiteLinks
{
	public const EMAIL = 'service@goinggreentransport.com';
	public const PHONE = '612-208-9557';

	/**
	 * Path per link key.
	 *
	 * @var array<string, string>
	 */
	private const PATHS = [
		'home' => '/',
		'services' => '/services/',
		'services-cultivation' => '/services/#cultivation',
		'services-lab' => '/services/#lab',
		'services-dispensary' => '/services/#dispensary',
		'locations' => '/cannabis-transport-locations-mn/',
		'flat-rate' => '/flat-rate-transport/',
		'on-demand' => '/on-demand-transport/',
		'how-it-works' => '/how-it-works/',
		'about' => '/about-us/',
		'resources' => '/cannabis-transport-resources/',
		'quote' => '/contact-us/',
		'faq' => '/#faq',
		'privacy' => '/privacy-policy-2/',
		'terms' => '/terms-and-conditions/',
		'guide-cultivator' => '/minnesota-cannabis-cultivators-complete-guide-to-transporting-your-product/',
		'guide-testing' => '/the-complete-guide-to-cannabis-testing-transport-in-minnesota/',
		'guide-manufacturer' => '/minnesota-cannabis-manufacturers-complete-guide-to-transporting-your-products/',
	];

	/**
	 * Absolute URL for a link key.
	 *
	 * @param string $key Link key.
	 *
	 * @return string
	 */
	public static function url(string $key): string
	{
		return \home_url(self::PATHS[$key] ?? '/');
	}
}

<?php

/**
 * The Theme/Frontend Enqueue specific functionality.
 *
 * @package GoingGreenTransportTheme\Enqueue\Theme
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Enqueue\Theme;

use GoingGreenTransportTheme\Config\Config;
use GoingGreenTransportThemeVendor\EightshiftLibs\Cache\ManifestCacheInterface;
use GoingGreenTransportThemeVendor\EightshiftLibs\Enqueue\Theme\AbstractEnqueueTheme;

/**
 * Class EnqueueTheme
 */
class EnqueueTheme extends AbstractEnqueueTheme
{
	/**
	 * Create a new instance.
	 *
	 * eightshift-libs 8.x injects the manifest *cache*; the previous
	 * `ManifestInterface` type no longer exists, which made the container
	 * silently drop this service (no theme CSS/JS on the front end).
	 *
	 * @param ManifestCacheInterface $manifestCache Inject manifest cache which holds data about assets from manifest.json.
	 */
	public function __construct(ManifestCacheInterface $manifestCache)
	{
		$this->manifestCache = $manifestCache;
	}

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_enqueue_scripts', [$this, 'enqueueStyles'], 10);
		\add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
	}

	/**
	 * Method that returns assets name used to prefix asset handlers.
	 *
	 * @return string
	 */
	public function getAssetsPrefix(): string
	{
		return Config::getProjectName();
	}

	/**
	 * Method that returns assets version for versioning asset handlers.
	 *
	 * @return string
	 */
	public function getAssetsVersion(): string
	{
		return Config::getProjectVersion();
	}
}

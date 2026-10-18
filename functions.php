<?php

/**
 * Theme Name: Going Green Transport
 * Description: Eightshift block theme for goinggreentransport.com
 * Author: Delta9 Digital
 * Author URI: https://delta9digital.com/
 * Version: 0.1.0
 * Text Domain: going-green-transport
 *
 * @package GoingGreenTransportTheme
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme;

use GoingGreenTransportTheme\Cache\ManifestCache;
use GoingGreenTransportTheme\Main\Main;
use GoingGreenTransportThemeVendor\EightshiftLibs\Cli\Cli;

/**
 * If this file is called directly, abort.
 */
if (! \defined('WPINC')) {
	die;
}

/**
 * Bailout, if the theme is not loaded via Composer.
 */
if (!\file_exists(__DIR__ . '/vendor/autoload.php')) {
	return;
}

/**
 * Require the Composer autoloader.
 */
$loader = require __DIR__ . '/vendor/autoload.php';

/**
 * Require the Composer autoloader for the prefixed libraries.
 */
if (\file_exists(__DIR__ . '/vendor-prefixed/autoload.php')) {
	require __DIR__ . '/vendor-prefixed/autoload.php';
}

/**
 * Set all the cache for the theme.
 */
if (\class_exists(ManifestCache::class)) {
	(new ManifestCache())->setAllCache();
}

/**
 * Begins execution of the theme.
 *
 * Since everything within the theme is registered via hooks,
 * then kicking off the theme from this point in the file does
 * not affect the page life cycle.
 */
if (\class_exists(Main::class)) {
	(new Main($loader->getPrefixesPsr4(), __NAMESPACE__))->register();
}

/**
 * Run all WPCLI commands.
 */
if (\class_exists(Cli::class)) {
	(new Cli())->load('theme-construct');
}

// Remove P tags from content.
\remove_filter('the_content', 'wpautop');


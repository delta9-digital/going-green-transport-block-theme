<?php

/**
 * Block pattern categories.
 *
 * @package GoingGreenTransportTheme\BlockPatterns
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\BlockPatterns;

use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * Class BlockPatternCategories
 */
class BlockPatternCategories implements ServiceInterface
{
	/**
	 * Category slug shared by all theme patterns.
	 *
	 * @var string
	 */
	public const CATEGORY = 'going-green';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerCategories']);
	}

	/**
	 * Register the pattern category.
	 *
	 * @return void
	 */
	public function registerCategories(): void
	{
		\register_block_pattern_category(
			self::CATEGORY,
			[
				'label' => \esc_html__('Going Green', 'going-green-transport'),
				'description' => \esc_html__('Sections from the Going Green Transport design system.', 'going-green-transport'),
			]
		);
	}
}

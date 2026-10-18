<?php

/**
 * Block styles: the component vocabulary of the comps on core blocks.
 *
 * Styles live in assets/styles/parts/theme/_block-styles.scss. Names are the targets for the
 * block-runner rules (docs/BLOCK-AUDIT.md).
 *
 * @package GoingGreenTransportTheme\BlockStyles
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\BlockStyles;

use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * Class BlockStyles
 */
class BlockStyles implements ServiceInterface
{
	/**
	 * Block name => [style slug => label].
	 *
	 * @var array<string, array<string, string>>
	 */
	private const STYLES = [
		'core/button' => [
			'ggt-split' => 'Split pill (lime)',
			'ggt-split-light' => 'Split pill (white, for lime backgrounds)',
			'ggt-outline' => 'Outline pill',
			'ggt-outline-light' => 'Outline pill (on dark)',
			'ggt-text' => 'Underlined text link',
			'ggt-arrow' => 'Text link with arrow circle',
			'ggt-row-link' => 'Row link (list of links)',
			'ggt-pill' => 'Pill (lime, no arrow)',
			'ggt-ghost-light' => 'Ghost pill (on dark)',
		],
		'core/paragraph' => [
			'ggt-label' => 'Eyebrow label',
			'ggt-mono-label' => 'Mono label',
			'ggt-chip' => 'Chip',
			'ggt-chip-outline' => 'Chip (outline)',
			'ggt-tag' => 'Tag',
		],
		'core/heading' => [
			'ggt-label' => 'Eyebrow label',
		],
		'core/group' => [
			'ggt-card' => 'Card',
			'ggt-panel' => 'Panel',
			'ggt-panel-dark' => 'Panel (forest)',
			'ggt-panel-lime' => 'Panel (lime)',
		],
		'core/list' => [
			'ggt-check' => 'Check list',
			'ggt-numbered' => 'Numbered list',
		],
		'core/image' => [
			'ggt-rounded' => 'Rounded',
		],
	];

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerBlockStyles']);
	}

	/**
	 * Register the block styles.
	 *
	 * @return void
	 */
	public function registerBlockStyles(): void
	{
		foreach (self::STYLES as $block => $styles) {
			foreach ($styles as $name => $label) {
				\register_block_style($block, ['name' => $name, 'label' => $label]);
			}
		}
	}
}

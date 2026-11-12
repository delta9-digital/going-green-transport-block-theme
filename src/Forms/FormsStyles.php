<?php

/**
 * Eightshift Forms (delta9-digital-forms) style options for the Forms block.
 *
 * Every Forms form on the site gets the GGT field look from assets/styles/parts/theme/_forms.scss (the same fields
 * as the footer sign-up form). A style picked here adds `es-block-forms--{value}` to the Forms block for layouts:
 * - quote: quote request, multi-step (numbered step bar) or single page (numbered section headings), with service
 *   cards and stop cards.
 *
 * @package GoingGreenTransportTheme\Forms
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Forms;

use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * Class FormsStyles
 */
class FormsStyles implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('es_forms_block_forms_style_options', [$this, 'getStyleOptions']);
	}

	/**
	 * Forms block style options.
	 *
	 * @param array<int, array<string, string>> $options Options from other code.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function getStyleOptions($options): array
	{
		$options = \is_array($options) ? $options : [];

		$options[] = [
			'label' => \__('Quote', 'going-green-transport'),
			'value' => 'quote',
		];

		return $options;
	}
}

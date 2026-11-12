<?php

/**
 * Site forms: put Eightshift Forms (delta9-digital-forms) forms where the hand-built ones are.
 *
 * Settings > General > "Site forms" picks a form for each slot; "Old form" keeps what was there:
 * - Footer sign-up: replaces the `.ggt-signup` form in the footer band (src/Signup/SignupForm.php).
 * - Quote page: replaces the embedded app.goinggreentransport.com quote iframe (embed-frame block).
 *
 * The swap happens at render time, so switching back is one setting, with no content to edit. If the
 * Forms plugin is off or the chosen form is gone, the old form shows.
 *
 * @package GoingGreenTransportTheme\Forms
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Forms;

use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * FormsSwap class.
 */
class FormsSwap implements ServiceInterface
{
	/**
	 * Option: form for the footer sign-up band.
	 */
	public const OPTION_FOOTER = 'ggt_forms_footer_form_id';

	/**
	 * Option: form for the quote page.
	 */
	public const OPTION_QUOTE = 'ggt_forms_quote_form_id';

	/**
	 * Forms post type and block.
	 */
	private const POST_TYPE = 'eightshift-forms';
	private const BLOCK = 'eightshift-forms/forms';

	/**
	 * Iframe sources that count as the quote embed.
	 */
	private const QUOTE_EMBED_HOST = 'app.goinggreentransport.com';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('render_block_core/html', [$this, 'swapFooter'], 10, 2);
		\add_filter('render_block_eightshift-boilerplate/embed-frame', [$this, 'swapQuote'], 10, 2);
		\add_action('admin_init', [$this, 'registerSettings']);
	}

	/**
	 * Footer band: the Forms form instead of the hand-built sign-up form.
	 *
	 * @param string $html Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 *
	 * @return string
	 */
	public function swapFooter(string $html, array $block): string
	{
		if (\strpos($html, 'class="ggt-signup"') === false) {
			return $html;
		}

		return $this->renderForm(self::OPTION_FOOTER, []) ?? $html;
	}

	/**
	 * Quote page: the Forms quote form instead of the app iframe.
	 *
	 * @param string $html Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 *
	 * @return string
	 */
	public function swapQuote(string $html, array $block): string
	{
		if (\strpos($html, self::QUOTE_EMBED_HOST) === false) {
			return $html;
		}

		$form = $this->renderForm(self::OPTION_QUOTE, ['formsStyle' => ['quote']]);

		return $form !== null ? '<div class="ggt-quote-form">' . $form . '</div>' : $html;
	}

	/**
	 * Settings > General > Site forms.
	 *
	 * @return void
	 */
	public function registerSettings(): void
	{
		\add_settings_section(
			'ggt_site_forms',
			\__('Site forms', 'going-green-transport'),
			static function (): void {
				echo '<p>' . \esc_html__('Which Forms form shows in each place. "Old form" keeps the hand-built footer form and the embedded quote app. Edit the forms themselves under Forms.', 'going-green-transport') . '</p>';
			},
			'general'
		);

		$slots = [
			self::OPTION_FOOTER => \__('Footer sign-up', 'going-green-transport'),
			self::OPTION_QUOTE => \__('Quote page', 'going-green-transport'),
		];

		foreach ($slots as $option => $label) {
			\register_setting('general', $option, [
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 0,
			]);

			\add_settings_field(
				$option,
				$label,
				[$this, 'renderSelect'],
				'general',
				'ggt_site_forms',
				['option' => $option, 'label_for' => $option]
			);
		}
	}

	/**
	 * Settings field: a dropdown of published Forms forms.
	 *
	 * @param array<string, string> $args Field args.
	 *
	 * @return void
	 */
	public function renderSelect(array $args): void
	{
		$option = $args['option'];
		$current = (int) \get_option($option, 0);

		$forms = \get_posts([
			'post_type' => self::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => 100,
			'orderby' => 'title',
			'order' => 'ASC',
		]);

		echo '<select id="' . \esc_attr($option) . '" name="' . \esc_attr($option) . '">';
		echo '<option value="0">' . \esc_html__('Old form', 'going-green-transport') . '</option>';

		foreach ($forms as $form) {
			echo '<option value="' . (int) $form->ID . '"' . \selected($current, $form->ID, false) . '>' . \esc_html($form->post_title) . ' (#' . (int) $form->ID . ')</option>';
		}

		echo '</select>';

		if (!\post_type_exists(self::POST_TYPE)) {
			echo '<p class="description">' . \esc_html__('The Forms plugin is not active, so the old form shows.', 'going-green-transport') . '</p>';
		}
	}

	/**
	 * Render the form chosen for a slot, or null to keep the old form.
	 *
	 * @param string $option Slot option.
	 * @param array<string, mixed> $attrs Extra Forms block attributes.
	 *
	 * @return string|null
	 */
	private function renderForm(string $option, array $attrs): ?string
	{
		$formId = (int) \get_option($option, 0);

		if (!$formId || !\WP_Block_Type_Registry::get_instance()->is_registered(self::BLOCK)) {
			return null;
		}

		$form = \get_post($formId);

		if (!$form || $form->post_type !== self::POST_TYPE || $form->post_status !== 'publish') {
			return null;
		}

		$attrs = ['formsFormPostId' => (string) $formId] + $attrs;

		return \render_block([
			'blockName' => self::BLOCK,
			'attrs' => $attrs,
			'innerBlocks' => [],
			'innerHTML' => '',
			'innerContent' => [],
		]);
	}
}

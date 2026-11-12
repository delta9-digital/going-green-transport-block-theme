<?php

/**
 * Partner Program application form (page /partner-program/).
 *
 * For licensed transporters, wholesalers and micro/mezzobusinesses that can move their own product
 * but would rather hand the runs to us, or send clients our way for a referral fee.
 *
 * - Markup: the `[ggt_partner_form]` shortcode (a core/shortcode block on the page).
 * - Submit: POST /wp-json/ggt/v1/partner (inline script below; plain form post without JS).
 * - Delivery: define GGT_PARTNER_ENDPOINT (and optionally GGT_PARTNER_TOKEN, sent as a Bearer token)
 *   in wp-config.php, or use the `ggt_partner_endpoint` filter, and the application is posted there
 *   as JSON. Until then, and whenever the API call fails, it is emailed to the service address.
 *   Every application also fires the `ggt_partner_application` action.
 * - Spam: a hidden honeypot field and five submissions per IP per ten minutes (same as SignupForm).
 *
 * @package GoingGreenTransportTheme\Partner
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Partner;

use GoingGreenTransportTheme\BlockPatterns\SiteLinks;
use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class PartnerForm
 */
class PartnerForm implements ServiceInterface
{
	public const HANDLE = 'going-green-partner';
	public const SHORTCODE = 'ggt_partner_form';
	public const ROUTE_NAMESPACE = 'ggt/v1';
	public const ROUTE = '/partner';

	/**
	 * Who is applying, value => label.
	 */
	public const PARTNER_TYPES = [
		'transporter' => 'Licensed transporter',
		'wholesaler' => 'Wholesaler',
		'microbusiness' => 'Microbusiness',
		'mezzobusiness' => 'Mezzobusiness',
		'other' => 'Other licensed business',
	];

	/**
	 * What they want from the program, value => label.
	 */
	public const INTERESTS = [
		'handoff' => 'Partner',
		'refer' => 'Refer',
		'both' => 'Both',
	];

	/**
	 * Rough transport volume, value => label.
	 */
	public const VOLUMES = [
		'starting' => 'Just getting started',
		'monthly' => 'A few runs a month',
		'weekly' => '1 to 3 runs a week',
		'daily' => '4+ runs a week',
	];

	private const RATE_LIMIT = 5;
	private const RATE_WINDOW = 10 * \MINUTE_IN_SECONDS;

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('rest_api_init', [$this, 'registerRoute']);
		\add_action('init', [$this, 'registerShortcode']);
		\add_action('wp_enqueue_scripts', [$this, 'enqueue']);
	}

	/**
	 * Shortcode.
	 *
	 * @return void
	 */
	public function registerShortcode(): void
	{
		\add_shortcode(self::SHORTCODE, [self::class, 'markup']);
	}

	/**
	 * Form markup.
	 *
	 * @return string
	 */
	public static function markup(): string
	{
		$id = static fn(string $name): string => 'ggt-partner-' . $name;

		$label = static fn(string $name, string $text, bool $required): string =>
			'<label for="' . $id($name) . '">' . \esc_html($text)
			. ($required ? '' : ' <span class="ggt-partner__optional">' . \esc_html__('(optional)', 'going-green-transport') . '</span>')
			. '</label>';

		$field = static fn(string $name, string $text, string $control, bool $required = true, string $mod = ''): string =>
			'<p class="ggt-partner__field' . ($mod !== '' ? ' ggt-partner__field--' . $mod : '') . '">' . $label($name, $text, $required) . $control . '</p>';

		$input = static fn(string $name, string $type, string $autocomplete, bool $required = true): string =>
			'<input id="' . $id($name) . '" name="' . $name . '" type="' . $type . '" autocomplete="' . $autocomplete . '"' . ($required ? ' required' : '') . ' maxlength="200">';

		$select = static function (string $name, array $options, string $placeholder, bool $required = true) use ($id): string {
			$html = '<option value=""' . ($required ? ' disabled' : '') . ' selected>' . \esc_html($placeholder) . '</option>';
			foreach ($options as $value => $text) {
				$html .= '<option value="' . \esc_attr($value) . '">' . \esc_html__($text, 'going-green-transport') . '</option>'; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			}

			return '<select id="' . $id($name) . '" name="' . $name . '"' . ($required ? ' required' : '') . '>' . $html . '</select>';
		};

		$interests = '';
		foreach (self::INTERESTS as $value => $text) {
			$interests .= '<label class="ggt-partner__choice"><input type="radio" name="interest" value="' . \esc_attr($value) . '" required'
				. ($value === 'handoff' ? ' checked' : '') . '><span>' . \esc_html__($text, 'going-green-transport') . '</span></label>'; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		}

		return '<form class="ggt-partner" id="partner-form" action="' . \esc_url(\rest_url(self::ROUTE_NAMESPACE . self::ROUTE)) . '" method="post">'
			. '<div class="ggt-partner__fields">'
			. '<fieldset class="ggt-partner__group ggt-partner__group--interest"><legend>' . \esc_html__('I want to', 'going-green-transport') . '</legend>'
			. '<div class="ggt-partner__choices">' . $interests . '</div></fieldset>'
			. '<div class="ggt-partner__grid">'
			. $field('name', \__('Your name', 'going-green-transport'), $input('name', 'text', 'name'))
			. $field('business', \__('Business name', 'going-green-transport'), $input('business', 'text', 'organization'))
			. $field('email', \__('Email', 'going-green-transport'), $input('email', 'email', 'email'))
			. $field('phone', \__('Phone', 'going-green-transport'), $input('phone', 'tel', 'tel'))
			. $field('partner_type', \__('Business type', 'going-green-transport'), $select('partner_type', self::PARTNER_TYPES, \__('Choose one', 'going-green-transport')))
			. $field('license_number', \__('OCM license number', 'going-green-transport'), $input('license_number', 'text', 'off', false), false)
			. $field('volume', \__('Transport volume', 'going-green-transport'), $select('volume', self::VOLUMES, \__('Choose one', 'going-green-transport'), false), false)
			. $field('regions', \__('Where you ship from and to', 'going-green-transport'), $input('regions', 'text', 'off', false), false)
			. $field('message', \__('Anything else we should know?', 'going-green-transport'), '<textarea id="' . $id('message') . '" name="message" rows="4" maxlength="2000"></textarea>', false, 'wide')
			. '</div>'
			. '<p class="ggt-partner__hp" aria-hidden="true"><label for="' . $id('website') . '">Website</label><input id="' . $id('website') . '" name="website" type="text" tabindex="-1" autocomplete="off"></p>'
			. '<div class="ggt-partner__actions">'
			. '<button class="ggt-partner__submit" type="submit">' . \esc_html__('Apply to Partner', 'going-green-transport') . '</button>'
			. '<p class="ggt-partner__note">' . \esc_html__('No commitment. We reply within one business day.', 'going-green-transport') . '</p>'
			. '</div>'
			. '</div>'
			. '<p class="ggt-partner__status" role="status" aria-live="polite" tabindex="-1"></p>'
			. '</form>';
	}

	/**
	 * REST route.
	 *
	 * @return void
	 */
	public function registerRoute(): void
	{
		// Optional fields default to '' so the handler never sees null.
		$text = static fn(bool $required, int $max = 200): array => [
			'type' => 'string',
			'required' => $required,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => static fn($v) => \is_string($v) && (!$required || \trim($v) !== '') && \mb_strlen($v) <= $max,
		] + ($required ? [] : ['default' => '']);
		$choice = static fn(array $options, bool $required): array => [
			'type' => 'string',
			'required' => $required,
			'validate_callback' => static fn($v) => \is_string($v) && (($v === '' && !$required) || isset($options[$v])),
		] + ($required ? [] : ['default' => '']);

		\register_rest_route(self::ROUTE_NAMESPACE, self::ROUTE, [
			'methods' => 'POST',
			'callback' => [$this, 'submit'],
			'permission_callback' => '__return_true',
			'args' => [
				'interest' => $choice(self::INTERESTS, true),
				'name' => $text(true),
				'business' => $text(true),
				'email' => ['type' => 'string', 'required' => true, 'format' => 'email', 'sanitize_callback' => 'sanitize_email'],
				'phone' => $text(true, 40),
				'partner_type' => $choice(self::PARTNER_TYPES, true),
				'license_number' => $text(false, 80),
				'volume' => $choice(self::VOLUMES, false),
				'regions' => $text(false),
				'message' => [
					'type' => 'string',
					'default' => '',
					'sanitize_callback' => 'sanitize_textarea_field',
					'validate_callback' => static fn($v) => \is_string($v) && \mb_strlen($v) <= 2000,
				],
				'website' => ['type' => 'string', 'default' => ''],
			],
		]);
	}

	/**
	 * Handle a submission.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit(WP_REST_Request $request)
	{
		// Bots fill the hidden field: answer as if it worked.
		if ((string) $request['website'] !== '') {
			return new WP_REST_Response(['ok' => true]);
		}

		$key = 'ggt_partner_' . \md5(\sanitize_text_field(\wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')))); // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
		$count = (int) \get_transient($key);
		if ($count >= self::RATE_LIMIT) {
			return new WP_Error('ggt_partner_rate', \__('Too many attempts. Please try again in a few minutes.', 'going-green-transport'), ['status' => 429]);
		}
		\set_transient($key, $count + 1, self::RATE_WINDOW);

		$interest = (string) $request['interest'];
		$type = (string) $request['partner_type'];
		$volume = (string) $request['volume'];

		$application = [
			'source' => 'partner-program',
			'interest' => $interest,
			'interest_label' => self::INTERESTS[$interest],
			'name' => (string) $request['name'],
			'business' => (string) $request['business'],
			'email' => (string) $request['email'],
			'phone' => (string) $request['phone'],
			'partner_type' => $type,
			'partner_type_label' => self::PARTNER_TYPES[$type],
			'license_number' => (string) $request['license_number'],
			'volume' => $volume,
			'volume_label' => self::VOLUMES[$volume] ?? '',
			'regions' => (string) $request['regions'],
			'message' => (string) $request['message'],
			'page' => \esc_url_raw((string) \wp_get_referer()),
			'submitted_at' => \gmdate('c'),
		];

		\do_action('ggt_partner_application', $application);

		if ($this->sendToApi($application) || $this->sendEmail($application)) {
			return new WP_REST_Response(['ok' => true]);
		}

		return new WP_Error(
			'ggt_partner_failed',
			\sprintf(
				/* translators: 1: email address, 2: phone number. */
				\__('Something went wrong. Please email us at %1$s or call %2$s.', 'going-green-transport'),
				SiteLinks::EMAIL,
				SiteLinks::PHONE
			),
			['status' => 502]
		);
	}

	/**
	 * Post the application to the configured API, if any.
	 *
	 * @param array<string, mixed> $application Application.
	 *
	 * @return bool True when the API accepted it.
	 */
	private function sendToApi(array $application): bool
	{
		$endpoint = (string) \apply_filters('ggt_partner_endpoint', \defined('GGT_PARTNER_ENDPOINT') ? (string) \constant('GGT_PARTNER_ENDPOINT') : '');
		if ($endpoint === '') {
			return false;
		}

		$headers = ['Content-Type' => 'application/json'];
		if (\defined('GGT_PARTNER_TOKEN')) {
			$headers['Authorization'] = 'Bearer ' . (string) \constant('GGT_PARTNER_TOKEN');
		}

		$response = \wp_remote_post($endpoint, ['headers' => $headers, 'body' => \wp_json_encode($application), 'timeout' => 8]);
		$code = \wp_remote_retrieve_response_code($response);

		return !\is_wp_error($response) && $code >= 200 && $code < 300;
	}

	/**
	 * Email the application to the service address.
	 *
	 * @param array<string, mixed> $application Application.
	 *
	 * @return bool
	 */
	private function sendEmail(array $application): bool
	{
		$lines = [
			'Wants to: ' . $application['interest_label'],
			'Name: ' . $application['name'],
			'Business: ' . $application['business'],
			'Business type: ' . $application['partner_type_label'],
			'OCM license: ' . ($application['license_number'] !== '' ? $application['license_number'] : '-'),
			'Email: ' . $application['email'],
			'Phone: ' . $application['phone'],
			'Volume: ' . ($application['volume_label'] !== '' ? $application['volume_label'] : '-'),
			'Ships from/to: ' . ($application['regions'] !== '' ? $application['regions'] : '-'),
			'',
			'Message:',
			$application['message'] !== '' ? $application['message'] : '-',
			'',
			'Page: ' . $application['page'],
		];

		return \wp_mail(
			SiteLinks::EMAIL,
			\sprintf('Partner Program application: %s, %s (%s)', $application['business'], $application['partner_type_label'], $application['interest_label']),
			\implode("\n", $lines),
			['Reply-To: ' . $application['name'] . ' <' . $application['email'] . '>']
		);
	}

	/**
	 * Inline script: submit with fetch, show the result in the status line.
	 *
	 * Only on pages that hold the form.
	 *
	 * @return void
	 */
	public function enqueue(): void
	{
		$post = \get_post();
		if (!$post || !\has_shortcode((string) $post->post_content, self::SHORTCODE)) { // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
			return;
		}

		\wp_register_script(self::HANDLE, false, [], null, ['in_footer' => true]); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		\wp_enqueue_script(self::HANDLE);

		$messages = \wp_json_encode([
			'sending' => \__('Sending…', 'going-green-transport'),
			'done' => \__('Thanks! Your application is in. We\'ll reach out within one business day to talk terms.', 'going-green-transport'),
			'error' => \sprintf(
				/* translators: 1: email address, 2: phone number. */
				\__('Something went wrong. Please email us at %1$s or call %2$s.', 'going-green-transport'),
				SiteLinks::EMAIL,
				SiteLinks::PHONE
			),
		]);

		\wp_add_inline_script(self::HANDLE, <<<JS
(() => {
	const t = {$messages};
	document.addEventListener('submit', async (e) => {
		const form = e.target.closest('.ggt-partner');
		if (!form) return;
		e.preventDefault();
		const status = form.querySelector('.ggt-partner__status');
		const button = form.querySelector('button[type="submit"]');
		const data = new FormData(form);
		button.disabled = true;
		form.classList.remove('is-error');
		status.textContent = t.sending;
		try {
			const res = await fetch(form.action, { method: 'POST', body: data, credentials: 'omit' });
			const body = await res.json().catch(() => ({}));
			if (!res.ok) throw new Error(body.message || t.error);
			form.reset();
			form.classList.add('is-done');
			status.textContent = t.done;
			status.focus();
			if (typeof window.gtag === 'function') window.gtag('event', 'ggt_partner_apply', { partner_type: data.get('partner_type'), interest: data.get('interest') });
		} catch (err) {
			form.classList.add('is-error');
			status.textContent = err.message && !/fetch|JSON/i.test(err.message) ? err.message : t.error;
		} finally {
			button.disabled = false;
		}
	});
})();
JS);
	}
}

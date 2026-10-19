<?php

/**
 * Footer sign-up form: name, business, licence type, email, newsletter opt-in.
 *
 * - Markup: SignupForm::markup(), placed in the footer band by SiteFooterPattern.
 * - Submit: POST /wp-json/ggt/v1/signup (inline script below; plain form post without JS).
 * - Delivery: when an API is set up, define GGT_SIGNUP_ENDPOINT (and optionally GGT_SIGNUP_TOKEN,
 *   sent as a Bearer token) in wp-config.php, or use the `ggt_signup_endpoint` filter: the lead is
 *   posted there as JSON. Until then, and whenever the API call fails, it is emailed to the service
 *   address. Every lead also fires the `ggt_signup` action.
 * - Spam: a hidden honeypot field and five submissions per IP per ten minutes.
 *
 * @package GoingGreenTransportTheme\Signup
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Signup;

use GoingGreenTransportTheme\BlockPatterns\SiteLinks;
use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class SignupForm
 */
class SignupForm implements ServiceInterface
{
	public const HANDLE = 'going-green-signup';
	public const ROUTE_NAMESPACE = 'ggt/v1';
	public const ROUTE = '/signup';

	/**
	 * Minnesota cannabis licence types (Office of Cannabis Management), value => label.
	 */
	public const LICENSES = [
		'cultivator' => 'Cultivator',
		'microbusiness' => 'Microbusiness',
		'mezzobusiness' => 'Mezzobusiness',
		'manufacturer' => 'Manufacturer',
		'wholesaler' => 'Wholesaler',
		'retailer' => 'Retailer',
		'testing-facility' => 'Testing Facility',
		'medical-combination' => 'Medical Cannabis Combination',
		'hemp' => 'Lower-Potency Hemp Edible',
		'other' => 'Other / Applying',
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
		\add_action('wp_enqueue_scripts', [$this, 'enqueue']);
	}

	/**
	 * Form markup (core/html content).
	 *
	 * @return string
	 */
	public static function markup(): string
	{
		$options = '<option value="" disabled selected>' . \esc_html__('License type', 'going-green-transport') . '</option>';
		foreach (self::LICENSES as $value => $label) {
			$options .= '<option value="' . \esc_attr($value) . '">' . \esc_html__($label, 'going-green-transport') . '</option>'; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		}

		// Compact: labels are for screen readers, placeholders show the field names.
		$field = static fn(string $name, string $label, string $control): string =>
			'<p class="ggt-signup__field ggt-signup__field--' . $name . '"><label class="screen-reader-text" for="ggt-signup-' . $name . '">' . \esc_html($label) . '</label>' . $control . '</p>';
		$input = static fn(string $name, string $type, string $autocomplete, string $label): string =>
			'<input id="ggt-signup-' . $name . '" name="' . $name . '" type="' . $type . '" autocomplete="' . $autocomplete . '" placeholder="' . \esc_attr($label) . '" required>';

		return '<form class="ggt-signup" action="' . \esc_url(\rest_url(self::ROUTE_NAMESPACE . self::ROUTE)) . '" method="post">'
			. '<div class="ggt-signup__row">'
			. $field('name', \__('Name', 'going-green-transport'), $input('name', 'text', 'name', \__('Name', 'going-green-transport')))
			. $field('business', \__('Business name', 'going-green-transport'), $input('business', 'text', 'organization', \__('Business name', 'going-green-transport')))
			. $field('license', \__('License type', 'going-green-transport'), '<select id="ggt-signup-license" name="license" required>' . $options . '</select>')
			. $field('email', \__('Email', 'going-green-transport'), $input('email', 'email', 'email', \__('Email', 'going-green-transport')))
			. '<p class="ggt-signup__check"><input id="ggt-signup-newsletter" name="newsletter" type="checkbox" value="1" checked><label for="ggt-signup-newsletter">'
			. \esc_html__('Subscribe to our newsletter', 'going-green-transport') . '</label></p>'
			. '<button class="ggt-signup__submit" type="submit">' . \esc_html__('Sign Up', 'going-green-transport') . '</button>'
			. '</div>'
			. '<p class="ggt-signup__hp" aria-hidden="true"><label for="ggt-signup-website">Website</label><input id="ggt-signup-website" name="website" type="text" tabindex="-1" autocomplete="off"></p>'
			. '<p class="ggt-signup__status" role="status" aria-live="polite"></p>'
			. '</form>';
	}

	/**
	 * REST route.
	 *
	 * @return void
	 */
	public function registerRoute(): void
	{
		$text = ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => static fn($v) => \is_string($v) && \trim($v) !== '' && \mb_strlen($v) <= 200];

		\register_rest_route(self::ROUTE_NAMESPACE, self::ROUTE, [
			'methods' => 'POST',
			'callback' => [$this, 'submit'],
			'permission_callback' => '__return_true',
			'args' => [
				'name' => $text,
				'business' => $text,
				'license' => ['type' => 'string', 'required' => true, 'enum' => \array_keys(self::LICENSES)],
				'email' => ['type' => 'string', 'required' => true, 'format' => 'email', 'sanitize_callback' => 'sanitize_email'],
				'newsletter' => ['type' => 'boolean', 'default' => false],
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

		$key = 'ggt_signup_' . \md5((string) ($_SERVER['REMOTE_ADDR'] ?? '')); // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$count = (int) \get_transient($key);
		if ($count >= self::RATE_LIMIT) {
			return new WP_Error('ggt_signup_rate', \__('Too many attempts. Please try again in a few minutes.', 'going-green-transport'), ['status' => 429]);
		}
		\set_transient($key, $count + 1, self::RATE_WINDOW);

		$lead = [
			'name' => (string) $request['name'],
			'business' => (string) $request['business'],
			'license' => (string) $request['license'],
			'license_label' => self::LICENSES[(string) $request['license']],
			'email' => (string) $request['email'],
			'newsletter' => (bool) $request['newsletter'],
			'page' => \esc_url_raw((string) \wp_get_referer()),
			'submitted_at' => \gmdate('c'),
		];

		\do_action('ggt_signup', $lead);

		if ($this->sendToApi($lead) || $this->sendEmail($lead)) {
			return new WP_REST_Response(['ok' => true]);
		}

		return new WP_Error(
			'ggt_signup_failed',
			\sprintf(
				/* translators: %s: email address. */
				\__('Something went wrong. Please email us at %s.', 'going-green-transport'),
				SiteLinks::EMAIL
			),
			['status' => 502]
		);
	}

	/**
	 * Post the lead to the configured API, if any.
	 *
	 * @param array<string, mixed> $lead Lead.
	 *
	 * @return bool True when the API accepted it.
	 */
	private function sendToApi(array $lead): bool
	{
		$endpoint = (string) \apply_filters('ggt_signup_endpoint', \defined('GGT_SIGNUP_ENDPOINT') ? (string) \constant('GGT_SIGNUP_ENDPOINT') : '');
		if ($endpoint === '') {
			return false;
		}

		$headers = ['Content-Type' => 'application/json'];
		if (\defined('GGT_SIGNUP_TOKEN')) {
			$headers['Authorization'] = 'Bearer ' . (string) \constant('GGT_SIGNUP_TOKEN');
		}

		$response = \wp_remote_post($endpoint, ['headers' => $headers, 'body' => \wp_json_encode($lead), 'timeout' => 8]);
		$code = \wp_remote_retrieve_response_code($response);

		return !\is_wp_error($response) && $code >= 200 && $code < 300;
	}

	/**
	 * Email the lead to the service address.
	 *
	 * @param array<string, mixed> $lead Lead.
	 *
	 * @return bool
	 */
	private function sendEmail(array $lead): bool
	{
		$lines = [
			'Name: ' . $lead['name'],
			'Business: ' . $lead['business'],
			'License type: ' . $lead['license_label'],
			'Email: ' . $lead['email'],
			'Newsletter: ' . ($lead['newsletter'] ? 'Yes' : 'No'),
			'Page: ' . $lead['page'],
		];

		return \wp_mail(
			SiteLinks::EMAIL,
			\sprintf('Website sign-up: %s, %s', $lead['name'], $lead['business']),
			\implode("\n", $lines),
			['Reply-To: ' . $lead['name'] . ' <' . $lead['email'] . '>']
		);
	}

	/**
	 * Inline script: submit with fetch, show the result in the status line.
	 *
	 * @return void
	 */
	public function enqueue(): void
	{
		\wp_register_script(self::HANDLE, false, [], null, ['in_footer' => true]); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		\wp_enqueue_script(self::HANDLE);

		$messages = \wp_json_encode([
			'sending' => \__('Sending…', 'going-green-transport'),
			'done' => \__('Thanks! We\'ll be in touch.', 'going-green-transport'),
			'error' => \sprintf(
				/* translators: %s: email address. */
				\__('Something went wrong. Please email us at %s.', 'going-green-transport'),
				SiteLinks::EMAIL
			),
		]);

		\wp_add_inline_script(self::HANDLE, <<<JS
(() => {
	const t = {$messages};
	document.addEventListener('submit', async (e) => {
		const form = e.target.closest('.ggt-signup');
		if (!form) return;
		e.preventDefault();
		const status = form.querySelector('.ggt-signup__status');
		const button = form.querySelector('button[type="submit"]');
		const data = new FormData(form);
		data.set('newsletter', form.elements.newsletter.checked ? '1' : '0');
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
			if (typeof window.gtag === 'function') window.gtag('event', 'ggt_signup', { license_type: data.get('license'), newsletter: data.get('newsletter') === '1' });
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

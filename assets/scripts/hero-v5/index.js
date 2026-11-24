/**
 * Home hero v5 (bin/home-hero-v5.php, _hero.scss).
 *
 * Review carousel (.ggt-reviews): cross-fades one Google review every 6s. No dots or arrows, so it pauses while
 * hovered or focused, while the tab is hidden, and never runs under prefers-reduced-motion (WCAG 2.2.2). Inactive
 * slides are aria-hidden and visibility:hidden. No aria-live: rotation must not interrupt a screen reader.
 *
 * Quote card (.ggt-hero-form, FormsSwap::swapHero): on the form's success event, swap the steps for the success
 * panel with the visitor's email, then "Start another quote" brings back step 1 and focuses First name.
 */

const INTERVAL = 6000;

const initReviews = (carousel) => {
	const slides = [...carousel.querySelectorAll(':scope > .ggt-review')];

	carousel.setAttribute('role', 'region');
	carousel.setAttribute('aria-roledescription', 'carousel');
	carousel.setAttribute('aria-label', 'Google reviews');

	if (slides.length < 2) {
		return;
	}

	let current = 0;
	let timer = null;
	let hovered = false;
	let focused = false;

	const show = (index) => {
		slides.forEach((slide, i) => {
			const active = i === index;
			slide.classList.toggle('is-active', active);
			if (active) {
				slide.removeAttribute('aria-hidden');
			} else {
				slide.setAttribute('aria-hidden', 'true');
			}
		});
		current = index;
	};

	show(0);
	carousel.classList.add('is-ready');

	const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

	const stop = () => {
		window.clearInterval(timer);
		timer = null;
	};

	const start = () => {
		stop();
		if (reduced.matches || hovered || focused || document.hidden) {
			return;
		}
		timer = window.setInterval(() => show((current + 1) % slides.length), INTERVAL);
	};

	carousel.addEventListener('mouseenter', () => { hovered = true; stop(); });
	carousel.addEventListener('mouseleave', () => { hovered = false; start(); });
	carousel.addEventListener('focusin', () => { focused = true; stop(); });
	carousel.addEventListener('focusout', () => { focused = false; start(); });
	document.addEventListener('visibilitychange', start);
	reduced.addEventListener?.('change', () => {
		if (reduced.matches) {
			show(0);
		}
		start();
	});

	start();
};

const initQuoteCard = (card) => {
	const form = card.querySelector('form');
	const panel = card.querySelector('.ggt-hero-form__success');

	if (!form || !panel) {
		return;
	}

	const heading = panel.querySelector('h3');

	// Errors: the plugin's global message becomes the alert; steps: announce and focus the new step's first field,
	// or the first invalid field when a step is refused.
	form.querySelector('.es-global-msg')?.setAttribute('role', 'alert');

	const live = document.createElement('p');
	live.className = 'screen-reader-text';
	live.setAttribute('aria-live', 'polite');
	card.appendChild(live);

	const focusable = 'input:not([type="hidden"]):not([tabindex="-1"]), select:not([hidden]), textarea, .choices[tabindex="0"]';
	const firstVisible = (root) => [...root.querySelectorAll(focusable)].find((el) => el.offsetParent !== null);
	const activeStep = () => form.querySelector('.es-step.es-form-is-active');
	const steps = [...form.querySelectorAll('.es-step')];
	let lastStep = activeStep();

	card.addEventListener('esFormsAfterFormSubmitEnd', () => {
		// Let the plugin finish switching steps and writing errors first.
		window.setTimeout(() => {
			const step = activeStep();
			if (!step || card.classList.contains('is-sent')) {
				return;
			}

			const invalid = step.querySelector('.es-form-has-error');
			if (invalid) {
				firstVisible(invalid)?.focus();
			} else if (step !== lastStep) {
				const index = steps.indexOf(step);
				const label = form.querySelectorAll('.es-progress-bar__item')[index]?.textContent.trim() || '';
				live.textContent = `Step ${index + 1} of ${steps.length}${label ? `, ${label}` : ''}`;
				firstVisible(step)?.focus();
			}
			lastStep = step;
		}, 60);
	});
	const emailOut = panel.querySelector('.ggt-hero-form__email');

	// Fired by Eightshift Forms after the final step is sent (before it resets the fields), bubbling from the form.
	card.addEventListener('esFormsAfterFormSubmitSuccess', () => {
		const email = form.querySelector('input[name="email"]')?.value || '';
		if (emailOut) {
			emailOut.textContent = email || 'your inbox';
		}
		card.classList.add('is-sent');
		panel.hidden = false;
		// After the plugin's own reset (which runs right after this event and moves focus).
		window.setTimeout(() => heading?.focus(), 100);
	});

	panel.querySelector('.ggt-hero-form__again')?.addEventListener('click', () => {
		card.classList.remove('is-sent');
		panel.hidden = true;
		// The plugin already reset the fields and went back to step 1.
		form.querySelector('input[name="first_name"]')?.focus();
	});
};

export const initHeroV5 = () => {
	document.querySelectorAll('.ggt-hero-v5 .ggt-reviews').forEach(initReviews);
	document.querySelectorAll('.ggt-hero-form').forEach(initQuoteCard);
};

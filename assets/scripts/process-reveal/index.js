/**
 * The Process section: scroll reveal.
 *
 * Progressive enhancement. On each `.ggt-sec--process` we add `.ggt-anim`
 * (which enables the muted/hidden start state in CSS, so visitors without JS
 * keep the normal design), then add `.is-in` to the heading and to each
 * `.ggt-step` as it scrolls into view — so the steps reveal 01 → 02 → 03 — and
 * `.is-active` to each step while it is above the middle of the viewport, so the
 * numbers and connectors turn from grey to green one at a time as you scroll.
 */
export function initProcessReveal() {
	const sections = document.querySelectorAll('.ggt-sec--process');

	if (!sections.length) {
		return;
	}

	sections.forEach((section) => {
		const targets = [];
		const heading = section.querySelector('.wp-block-heading');

		if (heading) {
			targets.push(heading);
		}

		section.querySelectorAll('.ggt-step').forEach((step) => targets.push(step));

		if (!targets.length) {
			return;
		}

		section.classList.add('ggt-anim');

		if (!('IntersectionObserver' in window)) {
			targets.forEach((el) => el.classList.add('is-in'));
			return;
		}

		const observer = new IntersectionObserver(
			(entries, obs) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting) {
						entry.target.classList.add('is-in');
						obs.unobserve(entry.target);
					}
				});
			},
			{ threshold: 0.3, rootMargin: '0px 0px -12% 0px' }
		);

		targets.forEach((el) => observer.observe(el));

		// Numbers go green one by one as each step passes the middle of the viewport, and grey
		// again when scrolled back below it. The root is the top half of the viewport; a step
		// that has scrolled out above it stays active.
		const active = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					const above = entry.rootBounds && entry.boundingClientRect.top < entry.rootBounds.top;
					entry.target.classList.toggle('is-active', entry.isIntersecting || above);
				});
			},
			{ threshold: 0, rootMargin: '0px 0px -50% 0px' }
		);

		section.querySelectorAll('.ggt-step').forEach((step) => active.observe(step));
	});
}

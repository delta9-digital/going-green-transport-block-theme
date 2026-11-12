/**
 * Filter grids (plugin filter-chips / filter-search / filter-grid: Locations hub, Resources): fade
 * the results in each time a chip or search changes them. Every card left showing fades, not just
 * the newly revealed ones, so narrowing the list (All -> one category) animates too.
 *
 * Watches the cards' `hidden` attribute rather than the controls, so it follows whatever the plugin
 * script decides; one filter pass is one batch of mutations, so one fade.
 */
const DURATION = 1000;
const KEYFRAMES = [
	{ opacity: 0, transform: 'translateY(8px)' },
	{ opacity: 1, transform: 'none' },
];

export const initFilterFade = () => {
	const grids = document.querySelectorAll('.ggt-filter-grid');

	if (!grids.length || typeof MutationObserver === 'undefined' || !Element.prototype.animate) {
		return;
	}

	const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

	grids.forEach((grid) => {
		const targets = () => [...grid.querySelectorAll('.wp-block-post, .block-filter-grid__empty')].filter((el) => !el.hidden);

		const observer = new MutationObserver(() => {
			if (reduceMotion.matches) {
				return;
			}

			targets().forEach((el) => {
				el.getAnimations().forEach((animation) => animation.cancel());
				el.animate(KEYFRAMES, { duration: DURATION, easing: 'cubic-bezier(0.2, 0.7, 0.3, 1)' });
			});
		});

		observer.observe(grid, { attributes: true, attributeFilter: ['hidden'], subtree: true });
	});
};

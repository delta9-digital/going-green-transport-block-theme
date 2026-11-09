/**
 * Sticky intro column with a heading (.ggt-sticky > .ggt-col-start): publish the heading's height on
 * the grid as --ggt-col-start-h so the second column starts level with the content under it.
 */
export const initColStart = () => {
	const heads = document.querySelectorAll('.is-layout-grid > .ggt-sticky > .ggt-col-start');

	if (!heads.length || typeof ResizeObserver === 'undefined') {
		return;
	}

	const observer = new ResizeObserver((entries) => {
		entries.forEach(({ target }) => {
			const grid = target.parentElement.parentElement;
			// Include the heading's bottom margin: that is the gap above the content under it.
			const { marginBottom } = getComputedStyle(target.lastElementChild || target);
			const height = target.getBoundingClientRect().height + parseFloat(marginBottom || '0');
			grid.style.setProperty('--ggt-col-start-h', `${Math.round(height)}px`);
		});
	});

	heads.forEach((head) => observer.observe(head));
};

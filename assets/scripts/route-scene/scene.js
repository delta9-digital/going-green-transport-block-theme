/**
 * How It Works route map (comps `GGT How It Works` and `- On-Demand`), driven by the plugin
 * scroll-story block: each `scroll-story:change` ({ step, progress }) re-renders the panel.
 *
 * Ported from the comps' renderVals(): a camera (translate + scale) over the Minnesota map,
 * region highlights, the variant's route (VARIANTS) drawn with progress, the van, city labels,
 * the HUD, and the emails each contact has received so far. Map space is the comps'
 * 71.55 x 79.77 viewBox; P() projects lon/lat into it. Labels keep a constant on-screen size
 * via --ggt-rs-s (the camera scale), see _route-scene.scss.
 */
import { sceneData } from './data';
import { regional } from './data-regional';
import { manufacturer } from './data-manufacturer';
import { onDemandDispensary } from './data-on-demand-dispensary';

const W = 71.55;
const H = 79.77;
const P = (lon, lat) => [10.7 + (lon + 96.25) * 8.85, 75.2 - (lat - 43.67) * 12.86];
const TRANSIT = 5;
const LAST = 7;

// data.js is generated from the comps; the lab run (regional) is overridden by data-regional.js.
const DATA = { ...sceneData, regional, manufacturer, 'on-demand-dispensary': onDemandDispensary };

const DULUTH_ROUTE = [[-92.10, 46.78], [-92.46, 46.72], [-92.76, 46.45], [-92.94, 46.01], [-92.97, 45.83], [-92.98, 45.51], [-92.99, 45.28], [-93.27, 44.98]];
const DULUTH_CITIES = [['Duluth', -92.10, 46.78, true], ['Minneapolis', -93.27, 44.98, true], ['St. Cloud', -94.16, 45.56], ['Rochester', -92.47, 44.02], ['Moorhead', -96.77, 46.87], ['Bemidji', -94.88, 47.47], ['Mankato', -94.0, 44.16], ['Hibbing', -92.94, 47.43], ['Hinckley', -92.94, 46.01, true], ['Worthington', -95.60, 43.62]];
// Region labels: regions carry a zone (they used to carry a route day).
const ZONE_PINS = [['ZONE 4', 21, 22.6], ['ZONE 2', 55.3, 31.6], ['ZONE 1', 34, 62], ['ZONE 5', 25.2, 85], ['ZONE 3', 57.9, 88]];
const REGIONS = ['central', 'northeast', 'northwest', 'southeast', 'southwest', 'metro'];

// Per variant: the route (lon/lat), its pickup and drop-off markers (route index, card side),
// numbered stops, region pins, the region highlighted at each step and the email column labels.
const VARIANTS = {
	'on-demand': {
		route: DULUTH_ROUTE,
		cities: DULUTH_CITIES,
		ends: [[0, 'pickup', 'Duluth', 'Pickup'], [7, 'dropoff', 'Minneapolis', 'Testing lab']],
		stops: [],
		pins: [],
		regionFor: ['all', 'northeast', 'northeast', 'northeast', 'northeast', 'northeast', 'metro', 'all'],
		chips: ['Mary · Pickup', 'Jane · Drop-off'],
	},
	regional: {
		route: DULUTH_ROUTE,
		cities: DULUTH_CITIES,
		ends: [[0, 'pickup', 'Duluth', 'Pickup'], [7, 'dropoff', 'Minneapolis', 'Testing lab']],
		stops: [[3, '2', 'pickup'], [5, '3', 'pickup']],
		pins: ZONE_PINS,
		regionFor: ['all', 'northeast', 'northeast', 'northeast', 'northeast', 'northeast', 'metro', 'all'],
		chips: ['Mary · Pickup', 'Jane · Drop-off'],
	},
	// The regional Duluth route with a manufacturer pickup and two dispensary drop-offs in
	// Minneapolis. The drive (step 5) ends at the first dispensary; the delivery step (6) covers the
	// dispensary-to-dispensary leg.
	manufacturer: {
		route: [...DULUTH_ROUTE.slice(0, 7), [-93.22, 45.045], [-93.22, 44.935]],
		cities: DULUTH_CITIES,
		// [route index, kind, title, tag, card side, [first step, last step] the card shows]; no
		// title = a dot only. The dispensaries are too close for a card each: one card.
		ends: [
			[0, 'pickup', 'Duluth', 'Manufacturer'],
			[8, 'dropoff', 'Minneapolis', '2 dispensaries'],
			[7, 'dropoff'],
		],
		stops: [[3, '2', 'pickup'], [5, '3', 'pickup']],
		pins: ZONE_PINS,
		regionFor: ['all', 'northeast', 'northeast', 'northeast', 'northeast', 'northeast', 'metro', 'all'],
		chips: ['Dana · Pickup', 'Dispensaries · Drop-offs'],
		// Route progress per step: [from, to, arrive] over the step's scroll progress; the van
		// reaches `to` at `arrive` (default 1) and waits there.
		legs: { 5: [0, 0.9495], 6: [0.9495, 1, 0.5] },
	},
	// On-Demand direct run on the same road from a Duluth manufacturer to the two Minneapolis
	// dispensaries: no other customers' stops, the on-demand map and camera.
	'on-demand-dispensary': {
		route: [...DULUTH_ROUTE.slice(0, 7), [-93.22, 45.045], [-93.22, 44.935]],
		cities: DULUTH_CITIES,
		ends: [
			[0, 'pickup', 'Duluth', 'Manufacturer'],
			[8, 'dropoff', 'Minneapolis', '2 dispensaries'],
			[7, 'dropoff'],
		],
		stops: [],
		pins: [],
		regionFor: ['all', 'northeast', 'northeast', 'northeast', 'northeast', 'northeast', 'metro', 'all'],
		chips: ['Dana · Pickup', 'Dispensaries · Drop-offs'],
		legs: { 5: [0, 0.9495], 6: [0.9495, 1, 0.5] },
	},
};

// Projected route geometry: points, segment lengths, start, end and bounding box.
const geometry = (lonLat) => {
	const route = lonLat.map(([lon, lat]) => P(lon, lat));
	const segments = route.slice(1).map((p, i) => Math.hypot(p[0] - route[i][0], p[1] - route[i][1]));
	const xs = route.map((p) => p[0]);
	const ys = route.map((p) => p[1]);
	return {
		route,
		segments,
		total: segments.reduce((a, b) => a + b, 0),
		A: route[0],
		B: route[route.length - 1],
		box: { x0: Math.min(...xs), x1: Math.max(...xs), y0: Math.min(...ys), y1: Math.max(...ys) },
	};
};

const pct = (point) => ({ left: `${(point[0] / W) * 100}%`, top: `${(point[1] / H) * 100}%` });

const node = (tag, className, parent, text) => {
	const el = document.createElement(tag);
	if (className) {
		el.className = className;
	}
	if (text !== undefined) {
		el.textContent = text;
	}
	parent?.append(el);
	return el;
};

// "1. Pickup: …" lines render as numbered rows.
const lineParts = (line) => {
	const m = /^(\d+)\.\s+(.*)$/.exec(line);
	return m ? { num: `${m[1]}.`, text: m[2] } : { num: '', text: line };
};

export class RouteScene {
	constructor(element) {
		this.el = element;
		this.variant = VARIANTS[element.dataset.variant] ? element.dataset.variant : 'regional';
		this.config = VARIANTS[this.variant];
		this.g = geometry(this.config.route);
		this.base = element.dataset.mapBase || '';
		this.story = element.closest('.js-block-scroll-story');
		this.data = DATA[this.variant];
		this.state = { step: 0, local: 0 };
		this.tipIds = { pick: '', drop: '' };
		this.geo = null;
	}

	// Point at fraction t along the route.
	along = (t) => {
		const { route, segments, total, B } = this.g;
		let d = t * total;
		for (let i = 0; i < segments.length; i++) {
			if (d <= segments[i]) {
				const k = d / segments[i];
				return [route[i][0] + (route[i + 1][0] - route[i][0]) * k, route[i][1] + (route[i + 1][1] - route[i][1]) * k];
			}
			d -= segments[i];
		}
		return B;
	};

	init = () => {
		this.build();
		this.el.classList.add('is-live');
		this.story?.addEventListener('scroll-story:change', (event) => {
			this.state = { step: event.detail.step, local: event.detail.progress };
			this.render();
		});
		new ResizeObserver(() => this.render()).observe(this.el);
		this.render();
	};

	build = () => {
		const cam = this.el.querySelector('.ggt-rs__cam');
		this.cam = cam;
		this.box = this.el.querySelector('.ggt-rs__box');

		this.regions = REGIONS.map((key) => {
			const img = node('img', 'ggt-rs__layer ggt-rs__region', cam);
			img.src = `${this.base}mn-region-${key}.svg`;
			img.alt = '';
			img.dataset.region = key;
			return img;
		});

		const svgNs = 'http://www.w3.org/2000/svg';
		const svg = document.createElementNS(svgNs, 'svg');
		svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
		svg.setAttribute('preserveAspectRatio', 'none');
		svg.setAttribute('class', 'ggt-rs__layer ggt-rs__route');
		svg.setAttribute('aria-hidden', 'true');
		const d = `M${this.g.route.map((p) => p.map((n) => n.toFixed(1)).join(',')).join(' L')}`;
		const path = (className, extra = {}) => {
			const p = document.createElementNS(svgNs, 'path');
			p.setAttribute('d', d);
			p.setAttribute('class', className);
			Object.entries(extra).forEach(([k, v]) => p.setAttribute(k, v));
			svg.append(p);
			return p;
		};
		this.ghost = [path('ggt-rs__ghost'), path('ggt-rs__ghost-dash')];
		this.draw = path('ggt-rs__draw', { pathLength: '1' });
		cam.append(svg);

		const endCities = this.config.ends.map(([, , city]) => city);
		this.cities = this.config.cities.map(([name, lon, lat, onRoute]) => {
			const [x, y] = P(lon, lat);
			const flip = x > W * 0.6 || name === 'Rochester' || name === 'Hinckley';
			const el = node('div', `ggt-rs__city${flip ? ' is-flipped' : ''}`, cam);
			Object.assign(el.style, pct([x, y]));
			node('span', 'ggt-rs__city-dot', el);
			node('span', 'ggt-rs__city-label', el, name);
			return { el, name, onRoute: Boolean(onRoute), isEnd: endCities.includes(name) };
		});

		this.pins = this.config.pins.map(([label, left, top]) => {
			const el = node('div', 'ggt-rs__pin', cam, label);
			Object.assign(el.style, { left: `${left}%`, top: `${top}%` });
			return el;
		});

		const end = (point, kind, city, tag, side, range) => {
			const dot = node('span', `ggt-rs__end ggt-rs__end--${kind}`, cam);
			Object.assign(dot.style, pct(point));
			if (!city) {
				return { dot, card: null, range };
			}
			const card = node('div', `ggt-rs__end-card ggt-rs__end-card--${kind}${side ? ` ggt-rs__end-card--${side}` : ''}`, cam);
			Object.assign(card.style, pct(point));
			node('span', 'ggt-rs__end-city', card, city);
			node('span', 'ggt-rs__end-tag', card, tag);
			return { dot, card, range };
		};
		this.ends = this.config.ends.map(([index, kind, city, tag, side, range]) => end(this.g.route[index], kind, city, tag, side, range));

		this.stops = this.config.stops.map(([index, label, kind]) => {
			const el = node('span', `ggt-rs__stop${kind ? ` ggt-rs__stop--${kind}` : ''}`, cam, label);
			Object.assign(el.style, pct(this.g.route[index]));
			return el;
		});

		this.van = node('span', 'ggt-rs__van', cam);
		node('span', 'ggt-rs__van-core', this.van);

		const column = (kind, label) => {
			const col = node('div', `ggt-rs__col ggt-rs__col--${kind}`, this.el);
			const tip = node('div', 'ggt-rs__tip', col);
			const chip = node('span', `ggt-rs__chip ggt-rs__chip--${kind}`, tip, label);
			const msg = node('div', 'ggt-rs__msg', tip);
			const head = node('div', 'ggt-rs__msg-head', msg);
			node('span', '', head, 'Email');
			const time = node('span', '', head);
			const subject = node('div', 'ggt-rs__msg-subject', msg);
			const lines = node('div', 'ggt-rs__msg-lines', msg);
			return { col, tip, msg, chip, label, time, subject, lines };
		};
		this.pick = column('pickup', this.config.chips[0]);
		this.drop = column('dropoff', this.config.chips[1]);

		const hud = node('div', 'ggt-rs__hud', this.el);
		const top = node('div', 'ggt-rs__hud-top', hud);
		const status = node('span', 'ggt-rs__hud-status', top);
		this.hudDot = node('span', 'ggt-rs__hud-dot', status);
		this.hudStatus = node('span', '', status);
		this.hudTime = node('span', 'ggt-rs__hud-time', top);
		this.hudLine = node('div', 'ggt-rs__hud-line', top);
		const bar = node('div', 'ggt-rs__hud-progress', hud);
		this.hudBar = node('div', 'ggt-rs__hud-bar', node('div', 'ggt-rs__hud-track', bar));
		this.hudStep = node('span', 'ggt-rs__hud-step', bar);
		this.hud = hud;

		// The live region describes the run for screen readers (the map itself is decorative).
		this.live = node('p', 'screen-reader-text', this.el);
		this.live.setAttribute('aria-live', 'polite');
	};

	measure = () => {
		const a = this.el.getBoundingClientRect();
		const b = this.box.getBoundingClientRect();
		const hb = this.hud.getBoundingClientRect().bottom - a.top;
		const pickRect = this.pick.col.getBoundingClientRect();
		const dropRect = this.drop.col.getBoundingClientRect();
		return {
			pw: a.width,
			ph: a.height,
			bx: b.left - a.left,
			by: b.top - a.top,
			bw: b.width,
			bh: b.height,
			hb,
			pB: pickRect.height ? pickRect.bottom - a.top : hb + 160,
			dT: dropRect.height ? dropRect.top - a.top : a.height - 160,
		};
	};

	// Camera that fits the whole route between the HUD/pickup emails and the drop-off emails.
	fitRoute = (G) => {
		const padX = 120;
		const half = 34;
		const { x0: mx0, x1: mx1, y0, y1 } = this.g.box;
		const cx = (mx0 + mx1) / 2;
		const spanX = (G.bw * (mx1 - mx0)) / W;
		const spanY = (G.bh * (y1 - y0)) / H;
		const yMlo = G.pB + 8 + half;
		const yMhi = G.ph - 12 - half;
		const yDlo = G.hb + 16 + half;
		const yDhi = G.dT - 8 - half;
		const sMin = Math.max(0.8, (yMlo - yDhi) / spanY);
		const sMax = Math.min(3.2, (yMhi - yDlo) / spanY, (G.pw - 24 - 2 * padX) / spanX);
		const sc = sMin <= sMax ? Math.min(Math.max(1.8, sMin), sMax) : sMax;
		const dist = spanY * sc;
		const lo = Math.max(yMlo, yDlo + dist);
		const hi = Math.min(yMhi, yDhi + dist);
		const yM = lo <= hi ? (lo + hi) / 2 : Math.min(yMhi, Math.max(yMlo, yDlo + dist));
		const fx = cx - (((G.pw / 2 - G.bx) / G.bw) - 0.5) * (W / sc);
		const fy = y1 - (((yM - G.by) / G.bh) - 0.5) * (H / sc);
		return [fx, fy, sc];
	};

	tip = (col, msg, key, delay) => {
		col.tip.hidden = !msg;
		if (!msg) {
			this.tipIds[key] = '';
			return false;
		}
		const id = (msg.chip || '') + msg.subject + msg.time;
		if (id === this.tipIds[key]) {
			return false;
		}
		this.tipIds[key] = id;
		// An email can name its own recipient (e.g. one of several drop-off contacts).
		col.chip.textContent = msg.chip || col.label;
		col.time.textContent = msg.time;
		col.subject.textContent = msg.subject;
		col.lines.replaceChildren(...msg.lines.map((line) => {
			const { num, text } = lineParts(line);
			const row = node('div', `ggt-rs__msg-line${num ? ' is-numbered' : ''}`);
			if (num) {
				node('span', 'ggt-rs__msg-num', row, num);
			}
			node('span', '', row, text);
			return row;
		}));
		// New email: slide it in.
		col.msg.classList.remove('is-in');
		window.setTimeout(() => col.msg.classList.add('is-in'), delay);
		return true;
	};

	render = () => {
		const { step, local } = this.state;
		const legs = this.config.legs || { [TRANSIT]: [0, 1] };
		let prog = step < TRANSIT ? 0 : 1;
		if (legs[step]) {
			const [from, to, arrive = 1] = legs[step];
			prog = from + (to - from) * Math.min(1, local / arrive);
		}
		const { hud, texts } = this.data(prog);

		// Emails each contact has received so far (the latest one shows).
		const seen = [];
		Object.keys(texts).forEach((k) => texts[k].forEach((t) => {
			if (Number(k) < step || (Number(k) === step && local >= t.at)) {
				seen.push(t);
			}
		}));
		const live = step >= 3 && step <= 6;
		const pk = seen.filter((t) => t.who !== 'dropoff').pop();
		const dp = seen.filter((t) => t.who !== 'pickup').pop();
		const pickNew = this.tip(this.pick, pk, 'pick', 40);
		this.tip(this.drop, dp, 'drop', pickNew ? 600 : 40);
		this.el.classList.toggle('is-compact', this.el.getBoundingClientRect().height < 640);

		const geo0 = this.geo || this.measure();
		const colW = Math.min(280, geo0.pw * 0.39);
		[this.pick.col, this.drop.col].forEach((col) => {
			col.style.width = `${colW}px`;
			col.style.maxHeight = `${Math.max(0, geo0.ph - geo0.hb - 24)}px`;
			col.style.opacity = live && (pk || dp) ? '1' : '0';
		});
		this.pick.col.style.top = `${geo0.hb + 10}px`;

		// HUD.
		const [status, line, dot, time] = hud[step] || hud[0];
		this.hudStatus.textContent = status;
		this.hudLine.textContent = line;
		this.hudDot.style.background = dot;
		this.hudTime.textContent = time;
		this.hudBar.style.width = `${Math.round(((step + (step === TRANSIT ? local : 0)) / LAST) * 100)}%`;
		let stepLabel = `Step ${String(step).padStart(2, '0')} / 06`;
		if (step === 0) {
			stepLabel = 'Start';
		} else if (step === LAST) {
			stepLabel = 'Complete';
		}
		this.hudStep.textContent = stepLabel;
		if (this.liveText !== `${status}: ${line}`) {
			this.liveText = `${status}: ${line}`;
			this.live.textContent = this.liveText;
		}

		// Camera: measure after the emails changed (they bound the fitted view).
		const G = this.measure();
		this.geo = G;
		const fit = this.fitRoute(G);
		const { A, box } = this.g;
		const mid = [(box.x0 + box.x1) / 2, (box.y0 + box.y1) / 2];
		const start = this.config.startView === 'route' ? [mid[0], mid[1], 2.2] : [A[0], A[1], 2.2];
		const views = [[W / 2, H / 2, 1], start, [mid[0], mid[1], 1.7], fit, fit, fit, fit, [W / 2, H / 2, 1]];
		Object.entries(this.config.views || {}).forEach(([i, [lon, lat, scale]]) => {
			views[i] = [...P(lon, lat), scale];
		});
		const [fx, fy, s] = views[step] || views[0];
		const tx = (0.5 - (fx / W) * s) * 100;
		const ty = (0.5 - (fy / H) * s) * 100;
		this.cam.style.transform = `translate(${tx}%, ${ty}%) scale(${s})`;
		this.cam.style.setProperty('--ggt-rs-s', String(s));

		// Map layers.
		const rf = this.config.regionFor[step] || 'all';
		this.regions.forEach((img) => {
			const on = rf !== 'all' && img.dataset.region === rf;
			img.style.opacity = on ? String(step === 6 ? 0.9 : 0.55) : '0';
		});
		let ghost = 0;
		if (step >= 1 && step <= 6) {
			ghost = 0.9;
		} else if (step === LAST) {
			ghost = 0.5;
		}
		this.ghost.forEach((p) => p.setAttribute('opacity', String(ghost)));
		this.draw.style.strokeDashoffset = (1 - prog).toFixed(4);

		const pinsOn = step === 0 || step === LAST;
		this.cities.forEach(({ el, onRoute, isEnd }) => {
			let op = onRoute ? 1 : 0.55;
			if (pinsOn || isEnd) {
				op = 0;
			}
			el.style.opacity = String(op);
		});
		this.pins.forEach((pin) => {
			pin.style.opacity = pinsOn ? '1' : '0';
		});
		this.ends.forEach(({ dot, card, range }) => {
			dot.style.opacity = step >= 1 ? '1' : '0';
			if (card) {
				card.style.opacity = (range ? step >= range[0] && step <= range[1] : step >= 1) ? '1' : '0';
			}
		});
		this.stops.forEach((el) => {
			el.style.opacity = live ? '1' : '0';
		});
		const van = this.along(prog);
		Object.assign(this.van.style, pct(van), { opacity: live ? '1' : '0' });
	};
}

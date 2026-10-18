/**
 * How It Works route map (comps `GGT How It Works` and `- On-Demand`), driven by the plugin
 * scroll-story block: each `scroll-story:change` ({ step, progress }) re-renders the panel.
 *
 * Ported from the comps' renderVals(): a camera (translate + scale) over the Minnesota map,
 * region highlights, the Duluth -> Minneapolis route drawn with progress, the van, city labels,
 * the HUD, and the emails each contact has received so far. Map space is the comps'
 * 71.55 x 79.77 viewBox; P() projects lon/lat into it. Labels keep a constant on-screen size
 * via --ggt-rs-s (the camera scale), see _route-scene.scss.
 */
import { sceneData } from './data';

const W = 71.55;
const H = 79.77;
const P = (lon, lat) => [10.7 + (lon + 96.25) * 8.85, 75.2 - (lat - 43.67) * 12.86];
const TRANSIT = 5;
const LAST = 7;

const ROUTE = [[-92.10, 46.78], [-92.46, 46.72], [-92.76, 46.45], [-92.94, 46.01], [-92.97, 45.83], [-92.98, 45.51], [-92.99, 45.28], [-93.27, 44.98]].map(([lon, lat]) => P(lon, lat));
const CITIES = [['Duluth', -92.10, 46.78, true], ['Minneapolis', -93.27, 44.98, true], ['St. Cloud', -94.16, 45.56], ['Rochester', -92.47, 44.02], ['Moorhead', -96.77, 46.87], ['Bemidji', -94.88, 47.47], ['Mankato', -94.0, 44.16], ['Hibbing', -92.94, 47.43], ['Hinckley', -92.94, 46.01, true], ['Worthington', -95.60, 43.62]];
const PINS = [['THU', 21, 22.6], ['TUE', 55.3, 31.6], ['MON', 34, 62], ['FRI', 25.2, 85], ['WED', 57.9, 88]];
const REGIONS = ['central', 'northeast', 'northwest', 'southeast', 'southwest', 'metro'];
const REGION_FOR = ['all', 'northeast', 'northeast', 'northeast', 'northeast', 'northeast', 'metro', 'all'];

const SEGMENTS = ROUTE.slice(1).map((p, i) => Math.hypot(p[0] - ROUTE[i][0], p[1] - ROUTE[i][1]));
const TOTAL = SEGMENTS.reduce((a, b) => a + b, 0);
const A = ROUTE[0];
const B = ROUTE[ROUTE.length - 1];
const pct = (point) => ({ left: `${(point[0] / W) * 100}%`, top: `${(point[1] / H) * 100}%` });

// Point at fraction t along the route.
const along = (t) => {
	let d = t * TOTAL;
	for (let i = 0; i < SEGMENTS.length; i++) {
		if (d <= SEGMENTS[i]) {
			const k = d / SEGMENTS[i];
			return [ROUTE[i][0] + (ROUTE[i + 1][0] - ROUTE[i][0]) * k, ROUTE[i][1] + (ROUTE[i + 1][1] - ROUTE[i][1]) * k];
		}
		d -= SEGMENTS[i];
	}
	return B;
};

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
		this.variant = element.dataset.variant === 'on-demand' ? 'on-demand' : 'regional';
		this.base = element.dataset.mapBase || '';
		this.story = element.closest('.js-block-scroll-story');
		this.data = sceneData[this.variant];
		this.state = { step: 0, local: 0 };
		this.tipIds = { pick: '', drop: '' };
		this.geo = null;
	}

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
		const d = `M${ROUTE.map((p) => p.map((n) => n.toFixed(1)).join(',')).join(' L')}`;
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

		this.cities = CITIES.map(([name, lon, lat, onRoute]) => {
			const [x, y] = P(lon, lat);
			const flip = x > W * 0.6 || name === 'Rochester' || name === 'Hinckley';
			const el = node('div', `ggt-rs__city${flip ? ' is-flipped' : ''}`, cam);
			Object.assign(el.style, pct([x, y]));
			node('span', 'ggt-rs__city-dot', el);
			node('span', 'ggt-rs__city-label', el, name);
			return { el, name, onRoute: Boolean(onRoute) };
		});

		this.pins = this.variant === 'regional' ? PINS.map(([day, left, top]) => {
			const el = node('div', 'ggt-rs__pin', cam, day);
			Object.assign(el.style, { left: `${left}%`, top: `${top}%` });
			return el;
		}) : [];

		const end = (point, kind, city, tag) => {
			const dot = node('span', `ggt-rs__end ggt-rs__end--${kind}`, cam);
			Object.assign(dot.style, pct(point));
			const card = node('div', `ggt-rs__end-card ggt-rs__end-card--${kind}`, cam);
			Object.assign(card.style, pct(point));
			node('span', 'ggt-rs__end-city', card, city);
			node('span', 'ggt-rs__end-tag', card, tag);
			return [dot, card];
		};
		this.ends = [...end(A, 'pickup', 'Duluth', 'Pickup'), ...end(B, 'dropoff', 'Minneapolis', 'Testing lab')];

		this.stops = this.variant === 'regional' ? [[ROUTE[3], '2'], [ROUTE[5], '3']].map(([point, label]) => {
			const el = node('span', 'ggt-rs__stop', cam, label);
			Object.assign(el.style, pct(point));
			return el;
		}) : [];

		this.van = node('span', 'ggt-rs__van', cam);
		node('span', 'ggt-rs__van-core', this.van);

		const column = (kind, label) => {
			const col = node('div', `ggt-rs__col ggt-rs__col--${kind}`, this.el);
			const tip = node('div', 'ggt-rs__tip', col);
			node('span', `ggt-rs__chip ggt-rs__chip--${kind}`, tip, label);
			const msg = node('div', 'ggt-rs__msg', tip);
			const head = node('div', 'ggt-rs__msg-head', msg);
			node('span', '', head, 'Email');
			const time = node('span', '', head);
			const subject = node('div', 'ggt-rs__msg-subject', msg);
			const lines = node('div', 'ggt-rs__msg-lines', msg);
			return { col, tip, msg, time, subject, lines };
		};
		this.pick = column('pickup', 'Mary · Pickup');
		this.drop = column('dropoff', 'Jane · Drop-off');

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
		const mx0 = Math.min(A[0], B[0]);
		const mx1 = Math.max(A[0], B[0]);
		const cx = (mx0 + mx1) / 2;
		const spanX = (G.bw * (mx1 - mx0)) / W;
		const spanY = (G.bh * Math.abs(B[1] - A[1])) / H;
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
		const fy = B[1] - (((yM - G.by) / G.bh) - 0.5) * (H / sc);
		return [fx, fy, sc];
	};

	tip = (col, msg, key, delay) => {
		col.tip.hidden = !msg;
		if (!msg) {
			this.tipIds[key] = '';
			return false;
		}
		const id = msg.subject + msg.time;
		if (id === this.tipIds[key]) {
			return false;
		}
		this.tipIds[key] = id;
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
		const prog = step < TRANSIT ? 0 : (step > TRANSIT ? 1 : local);
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
		const mid = [(A[0] + B[0]) / 2, (A[1] + B[1]) / 2];
		const views = [[W / 2, H / 2, 1], [A[0], A[1], 2.2], [mid[0], mid[1], 1.7], fit, fit, fit, fit, [W / 2, H / 2, 1]];
		const [fx, fy, s] = views[step] || views[0];
		const tx = (0.5 - (fx / W) * s) * 100;
		const ty = (0.5 - (fy / H) * s) * 100;
		this.cam.style.transform = `translate(${tx}%, ${ty}%) scale(${s})`;
		this.cam.style.setProperty('--ggt-rs-s', String(s));

		// Map layers.
		const rf = REGION_FOR[step] || 'all';
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
		this.cities.forEach(({ el, name, onRoute }) => {
			let op = onRoute ? 1 : 0.55;
			if (pinsOn || name === 'Duluth' || name === 'Minneapolis') {
				op = 0;
			}
			el.style.opacity = String(op);
		});
		this.pins.forEach((pin) => {
			pin.style.opacity = pinsOn ? '1' : '0';
		});
		this.ends.forEach((el) => {
			el.style.opacity = step >= 1 ? '1' : '0';
		});
		this.stops.forEach((el) => {
			el.style.opacity = live ? '1' : '0';
		});
		const van = along(prog);
		Object.assign(this.van.style, pct(van), { opacity: live ? '1' : '0' });
	};
}

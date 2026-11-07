/**
 * Route map variant `on-demand-dispensary` (hand-written; data.js is generated from the comps): an
 * On-Demand direct run from a manufacturer in Duluth to two dispensaries in Minneapolis, with no
 * other customers' stops. Same shape as data.js: (prog) => { hud, texts }; `chip` names the
 * drop-off contact an email went to, and each dispensary gets its own ETA window.
 *
 * prog runs 0 -> 0.9495 over the drive (step 5, ending at the first dispensary) and on to 1 over
 * the first half of the delivery step (6); see `legs` in scene.js. `at` is the step's own progress.
 */

const DRIVER = 'Marcus';
const PICK = 'Lake Superior Infusions';
const STOPS = [
	{ name: 'Mill City Cannabis Co.', contact: 'Jordan', window: '10:40–11:10 AM', done: '10:55 AM' },
	{ name: 'Lake Street Cannabis Co.', contact: 'Sam', window: '11:05–11:35 AM', done: '11:20 AM' },
];
const chip = (stop) => `${stop.contact} · ${stop.name}`;
// End a sentence after a name that may already end with a period ("Co.").
const sentence = (text) => (text.endsWith('.') ? text : `${text}.`);
const color = (name) => `var(--wp--preset--color--${name})`;
const day = (clock) => `Tue, Oct 20 · ${clock}`;

// Route progress at the first dispensary (scene.js route point 7).
const FIRST = 0.9495;

const time = (minutes) => `TUE ${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;

export const onDemandDispensary = (prog) => {
	const [first, second] = STOPS;
	const driving = Math.min(1, prog / FIRST);
	const delivering = Math.max(0, (prog - FIRST) / (1 - FIRST));
	const done = prog >= 1;

	return {
		hud: [
			['On-demand', 'Any day · direct runs', color('gray-500'), ''],
			['Request received', 'Pickup: licensed manufacturer, Duluth', color('gold-500'), 'MON 10:14'],
			['Confirmed', 'Dedicated team assigned · 2 dispensaries notified', color('gold-500'), 'MON 11:02'],
			['Dispatched', 'Direct run · ETA windows sent', color('lime-700'), 'TUE 07:40'],
			['At pickup', 'Manifests verified for 2 drop-offs', color('lime-700'), 'TUE 08:31'],
			['In transit', 'GPS live · your stops only', color('green-500'), time(8 * 60 + 40 + Math.round(driving * 135))],
			[
				done ? 'Delivered' : 'Delivering',
				done ? 'Signed handoffs · every contact emailed' : `Stop 2 of 3 · next: ${second.name}`,
				color(done ? 'green-800' : 'green-500'),
				time(10 * 60 + 55 + Math.round(delivering * 25)),
			],
			['Documented', 'Run record stored in CRM', color('green-800'), 'TUE 11:22'],
		],
		texts: {
			3: [
				{ who: 'both', at: 0, time: day('7:30 AM'), subject: 'Request dispatched', lines: [`Going Green Transport assigned your request to ${DRIVER} (direct run, 3 stops).`, `1. Pickup: ${PICK} · Duluth`, `2. Dropoff: ${first.name} · Minneapolis`, `3. Dropoff: ${second.name} · Minneapolis`] },
				{ who: 'pickup', at: 0.3, time: day('7:40 AM'), subject: 'On the way · ETA window', lines: [`Hi Dana, ${DRIVER} is on the way to ${PICK}.`, 'Pickup ETA window: 8:15–8:45 AM.'] },
				{ who: 'dropoff', chip: chip(first), at: 0.45, time: day('7:40 AM'), subject: 'Your ETA window', lines: [`Hi ${first.contact}, your delivery from ${PICK} is scheduled for today.`, `ETA window: ${first.window}.`] },
				{ who: 'pickup', at: 0.8, time: day('8:20 AM'), subject: '5 minutes away', lines: [`Hi Dana, ${DRIVER} is about 5 minutes away.`, 'Please have product staged for handoff.'] },
			],
			4: [
				{ who: 'both', at: 0.3, time: day('8:31 AM'), subject: '✓ Pickup completed', lines: [`${DRIVER} completed the pickup at Duluth, MN at 8:31 AM.`, 'Progress: 1 of 3 stops complete.'] },
				{ who: 'dropoff', chip: chip(second), at: 0.65, time: day('8:33 AM'), subject: 'Your ETA window', lines: [`Hi ${second.contact}, your delivery from ${PICK} is on the way.`, `ETA window: ${second.window}.`] },
			],
			5: [
				{ who: 'dropoff', chip: chip(first), at: 0.45, time: day('9:40 AM'), subject: 'On schedule', lines: [`Hi ${first.contact}, ${DRIVER} is driving straight to you, no other stops.`, `ETA window: ${first.window} (on time).`] },
				{ who: 'dropoff', chip: chip(first), at: 0.85, time: day('10:40 AM'), subject: '15 minutes away', lines: [sentence(`Hi ${first.contact}, ${DRIVER} is about 15 minutes from ${first.name}`), `Inside your ETA window (${first.window}).`] },
			],
			6: [
				{ who: 'dropoff', chip: chip(first), at: 0, time: day(first.done), subject: '✓ Delivered', lines: [`Hi ${first.contact}, your delivery is complete at ${first.done} by ${DRIVER}.`, 'Product delivered: See the manifest for details.'] },
				{ who: 'dropoff', chip: chip(second), at: 0.25, time: day('10:56 AM'), subject: "You're next · ETA window", lines: [`Hi ${second.contact}, ${DRIVER} just completed the ${first.name} stop. You're next.`, `ETA window: ${second.window} (on time).`] },
				{ who: 'dropoff', chip: chip(second), at: 0.6, time: day(second.done), subject: '✓ Delivered', lines: [`Hi ${second.contact}, your delivery is complete at ${second.done} by ${DRIVER}.`, 'Product delivered: See the manifest for details.'] },
				{ who: 'pickup', at: 0.6, time: day(second.done), subject: '✓ All stops delivered', lines: ['Hi Dana, both deliveries on your run are complete.', `1. ${first.name} · ${first.done}`, `2. ${second.name} · ${second.done}`] },
			],
		},
	};
};

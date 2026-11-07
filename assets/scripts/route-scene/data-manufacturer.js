/**
 * Route map variant `manufacturer` (hand-written; data.js is generated from the comps): the Zone 2
 * Northeast route from a manufacturer in Duluth, through the Hinckley and North Branch stops, to
 * two dispensaries in Minneapolis. Same shape as data.js: (prog) => { hud: [[status, line, dot
 * colour, time] per step], texts: { step: [{ who, at, time, subject, lines, chip? }] } }. `chip`
 * names the drop-off contact an email went to; each dispensary gets its own ETA window.
 *
 * prog runs 0 -> 0.9495 over the drive (step 5, ending at the first dispensary) and on to 1 over
 * the first half of the delivery step (6); see `legs` in scene.js. `at` is the step's own progress.
 * Emails are spaced like the lab run's so the cards don't churn.
 */

const DRIVER = 'Marcus';
const PICK = 'Lake Superior Infusions';
const STOPS = [
	{ name: 'Mill City Cannabis Co.', area: 'Northeast Minneapolis', contact: 'Jordan', window: '10:45–11:15 AM', done: '11:05 AM' },
	{ name: 'Lake Street Cannabis Co.', area: 'Longfellow, Minneapolis', contact: 'Sam', window: '11:15–11:45 AM', done: '11:30 AM' },
];
const chip = (stop) => `${stop.contact} · ${stop.name}`;
// End a sentence after a name that may already end with a period ("Co.").
const sentence = (text) => (text.endsWith('.') ? text : `${text}.`);
const color = (name) => `var(--wp--preset--color--${name})`;

// Route progress where each stop is reached (scene.js route points 3, 5, 7).
const AT = { hinckley: 0.4833, northBranch: 0.7136, first: 0.9495 };

const time = (minutes) => `TUE ${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;

export const manufacturer = (prog) => {
	const [first, second] = STOPS;

	// Drive: 8:40 at the pickup to 11:05 at the first dispensary; then 25 minutes to the second.
	const driving = Math.min(1, prog / AT.first);
	const delivering = Math.max(0, (prog - AT.first) / (1 - AT.first));

	let nextStop = `Minneapolis · ${first.name}`;
	if (prog < AT.hinckley) {
		nextStop = 'Hinckley';
	} else if (prog < AT.northBranch) {
		nextStop = 'North Branch';
	}

	const done = prog >= 1;

	return {
		hud: [
			['Regional routes', 'Five regions · five zones', color('gray-500'), ''],
			['Zone 2', 'Duluth · Northeast route', color('gold-500'), 'MON 09:00'],
			['Stops booked', '2 Minneapolis drop-offs booked before 3pm', color('gold-500'), 'MON 11:02'],
			['Route departed', 'Stop 1 of 5 · ETA windows sent', color('lime-700'), 'TUE 07:40'],
			['At pickup', 'Manifests verified · 1 of 5 stops complete', color('lime-700'), 'TUE 08:31'],
			['On route', `Next stop: ${nextStop} · GPS live`, color('green-500'), time(8 * 60 + 40 + Math.round(driving * 145))],
			[
				done ? 'Delivered' : 'Delivering',
				done ? 'Stop 5 of 5 · every contact emailed' : `Stop 4 of 5 · next: ${second.name}`,
				color(done ? 'green-800' : 'green-500'),
				time(11 * 60 + 5 + Math.round(delivering * 25)),
			],
			['Documented', 'Route record stored in CRM', color('green-800'), 'TUE 11:32'],
		],
		texts: {
			3: [
				{ who: 'both', at: 0, time: '7:30 AM', subject: 'Route dispatched', lines: [`Going Green Transport dispatched the Northeast route to ${DRIVER} (5 stops).`, `1. Pickup: ${PICK} · Duluth`, '2. Stop · Hinckley', '3. Stop · North Branch', `4. Dropoff: ${first.name} · Minneapolis`, `5. Dropoff: ${second.name} · Minneapolis`] },
				{ who: 'pickup', at: 0.3, time: '7:40 AM', subject: 'On the way · ETA window', lines: [`Hi Dana, ${DRIVER} is on the way. You are stop 1 of 5 on today's Northeast route.`, 'Pickup ETA window: 8:15–8:45 AM.'] },
				{ who: 'dropoff', chip: chip(first), at: 0.45, time: '7:40 AM', subject: 'Your ETA window', lines: [`Hi ${first.contact}, your delivery from ${PICK} is on today's Northeast route.`, `ETA window: ${first.window}.`] },
				{ who: 'pickup', at: 0.8, time: '8:20 AM', subject: '5 minutes away', lines: [`Hi Dana, ${DRIVER} is about 5 minutes away.`, 'Please have product staged for handoff.'] },
			],
			4: [
				{ who: 'both', at: 0.3, time: '8:31 AM', subject: '✓ Pickup completed', lines: [`${DRIVER} completed the pickup at Duluth, MN at 8:31 AM.`, 'Manifests verified for 2 drop-offs.', 'Progress: 1 of 5 stops complete.'] },
				{ who: 'dropoff', chip: chip(second), at: 0.65, time: '8:33 AM', subject: 'Your ETA window', lines: [`Hi ${second.contact}, your delivery from ${PICK} is on the way.`, `ETA window: ${second.window}.`] },
			],
			5: [
				{ who: 'dropoff', chip: chip(first), at: 0.5, time: '9:42 AM', subject: 'Route update', lines: [`Hi ${first.contact}, ${DRIVER} completed stop 2 of 5 in Hinckley.`, `Your delivery is on schedule (ETA window ${first.window}).`] },
				{ who: 'dropoff', chip: chip(first), at: 0.85, time: '10:50 AM', subject: '15 minutes away', lines: [sentence(`Hi ${first.contact}, ${DRIVER} is about 15 minutes from ${first.name}`), `Inside your ETA window (${first.window}).`] },
			],
			6: [
				{ who: 'dropoff', chip: chip(first), at: 0, time: first.done, subject: '✓ Delivered', lines: [`Hi ${first.contact}, your delivery is complete at ${first.done} by ${DRIVER}.`, 'Product delivered: See the manifest for details.'] },
				{ who: 'dropoff', chip: chip(second), at: 0.25, time: '11:06 AM', subject: "You're next · ETA window", lines: [`Hi ${second.contact}, ${DRIVER} just completed the ${first.name} stop. You're next.`, `ETA window: ${second.window} (on time).`] },
				{ who: 'dropoff', chip: chip(second), at: 0.6, time: second.done, subject: '✓ Delivered', lines: [`Hi ${second.contact}, your delivery is complete at ${second.done} by ${DRIVER}.`, 'Product delivered: See the manifest for details.'] },
				{ who: 'pickup', at: 0.6, time: second.done, subject: '✓ All stops delivered', lines: ['Hi Dana, both Minneapolis deliveries on today\'s route are complete.', `1. ${first.name} · ${first.done}`, `2. ${second.name} · ${second.done}`] },
			],
		},
	};
};

/**
 * Route map variant `manufacturer` (hand-written; data.js is generated from the comps): the Zone 2
 * Northeast route from a manufacturer in Duluth, through the Hinckley and North Branch stops, to
 * three dispensaries in Minneapolis. Same shape as data.js: (prog) => { hud: [[status, line, dot
 * colour, time] per step], texts: { step: [{ who, at, time, subject, lines, chip? }] } }. `chip`
 * names the drop-off contact an email went to; each dispensary gets its own ETA window.
 *
 * prog runs 0 -> 0.9029 over the drive (step 5, ending at the first dispensary) and on to 1 over
 * the Minneapolis deliveries (step 6); see `legs` in scene.js. `at` is the step's own progress.
 */

const DRIVER = 'Marcus';
const PICK = 'Lake Superior Infusions';
const STOPS = [
	{ name: 'Mill City Cannabis Co.', area: 'Northeast Minneapolis', contact: 'Jordan', window: '10:45–11:15 AM', done: '11:05 AM' },
	{ name: 'Linden Hills Dispensary', area: 'Linden Hills, Minneapolis', contact: 'Priya', window: '11:10–11:40 AM', done: '11:28 AM' },
	{ name: 'Lake Street Cannabis Co.', area: 'Longfellow, Minneapolis', contact: 'Sam', window: '11:30 AM–12:00 PM', done: '11:48 AM' },
];
const chip = (stop) => `${stop.contact} · ${stop.name}`;
// End a sentence after a name that may already end with a period ("Co.").
const stop = (text) => (text.endsWith('.') ? text : `${text}.`);
const color = (name) => `var(--wp--preset--color--${name})`;

// Route progress where each stop is reached (scene.js route points 3, 5, 7, 8, 9).
const AT = { hinckley: 0.4596, northBranch: 0.6786, first: 0.9029, second: 0.9594 };

const time = (minutes) => `TUE ${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;

export const manufacturer = (prog) => {
	const [first, second, third] = STOPS;

	// Drive: 8:40 at the pickup to 11:05 at the first dispensary; deliveries: 11:05 to 11:48.
	const driving = Math.min(1, prog / AT.first);
	const delivering = Math.max(0, (prog - AT.first) / (1 - AT.first));
	const driveClock = time(8 * 60 + 40 + Math.round(driving * 145));
	const deliverClock = time(11 * 60 + 5 + Math.round(delivering * 43));

	let nextStop = `Minneapolis · ${first.name}`;
	if (prog < AT.hinckley) {
		nextStop = 'Hinckley';
	} else if (prog < AT.northBranch) {
		nextStop = 'North Branch';
	}

	let delivery = ['Delivering', `Stop 4 of 6 · next: ${second.name}`];
	if (prog >= 1) {
		delivery = ['Delivered', 'Stop 6 of 6 · every contact emailed'];
	} else if (prog >= AT.second) {
		delivery = ['Delivering', `Stop 5 of 6 · next: ${third.name}`];
	}

	return {
		hud: [
			['Regional routes', 'Five regions · five zones', color('gray-500'), ''],
			['Zone 2', 'Duluth · Northeast route', color('gold-500'), 'MON 09:00'],
			['Stops booked', '3 Minneapolis drop-offs booked before 3pm', color('gold-500'), 'MON 11:02'],
			['Route departed', 'Stop 1 of 6 · ETA windows sent', color('lime-700'), 'TUE 07:40'],
			['At pickup', 'Manifests verified · 1 of 6 stops complete', color('lime-700'), 'TUE 08:31'],
			['On route', `Next stop: ${nextStop} · GPS live`, color('green-500'), driveClock],
			[delivery[0], delivery[1], color(prog >= 1 ? 'green-800' : 'green-500'), deliverClock],
			['Documented', 'Route record stored in CRM', color('green-800'), 'TUE 11:50'],
		],
		texts: {
			3: [
				{ who: 'both', at: 0, time: '7:30 AM', subject: 'Route dispatched', lines: [`Going Green Transport dispatched the Northeast route to ${DRIVER} (6 stops).`, `1. Pickup: ${PICK} · Duluth`, '2. Stop · Hinckley', '3. Stop · North Branch', `4. Dropoff: ${first.name} · Minneapolis`, `5. Dropoff: ${second.name} · Minneapolis`, `6. Dropoff: ${third.name} · Minneapolis`] },
				{ who: 'pickup', at: 0.25, time: '7:40 AM', subject: 'On the way · ETA window', lines: [`Hi Dana, ${DRIVER} is on the way. You are stop 1 of 6 on today's Northeast route.`, 'Pickup ETA window: 8:15–8:45 AM.'] },
				{ who: 'dropoff', chip: chip(first), at: 0.4, time: '7:40 AM', subject: 'Your ETA window', lines: [`Hi ${first.contact}, your delivery from ${PICK} is on today's Northeast route.`, `ETA window: ${first.window}.`] },
				{ who: 'pickup', at: 0.55, time: '8:10 AM', subject: '15 minutes away', lines: [`Hi Dana, ${DRIVER} is about 15 minutes from ${PICK}.`] },
				{ who: 'pickup', at: 0.8, time: '8:20 AM', subject: '5 minutes away', lines: [`Hi Dana, ${DRIVER} is about 5 minutes away.`, 'Please have product staged for handoff.'] },
			],
			4: [
				{ who: 'both', at: 0.25, time: '8:31 AM', subject: '✓ Pickup completed', lines: [`${DRIVER} completed the pickup at Duluth, MN at 8:31 AM.`, 'Manifests verified for 3 drop-offs.', 'Progress: 1 of 6 stops complete.'] },
				{ who: 'dropoff', chip: chip(second), at: 0.55, time: '8:33 AM', subject: 'Your ETA window', lines: [`Hi ${second.contact}, your delivery from ${PICK} is on the way.`, `ETA window: ${second.window}.`] },
				{ who: 'dropoff', chip: chip(third), at: 0.8, time: '8:33 AM', subject: 'Your ETA window', lines: [`Hi ${third.contact}, your delivery from ${PICK} is on the way.`, `ETA window: ${third.window}.`] },
			],
			5: [
				{ who: 'dropoff', chip: chip(first), at: 0.5, time: '9:42 AM', subject: 'Route update', lines: [`Hi ${first.contact}, ${DRIVER} completed stop 2 of 6 in Hinckley.`, `Your delivery is on schedule (ETA window ${first.window}).`] },
				{ who: 'dropoff', chip: chip(first), at: 0.9, time: '10:50 AM', subject: '15 minutes away', lines: [stop(`Hi ${first.contact}, ${DRIVER} is about 15 minutes from ${first.name}`), `Inside your ETA window (${first.window}).`] },
			],
			6: [
				{ who: 'dropoff', chip: chip(first), at: 0, time: first.done, subject: '✓ Delivered', lines: [`Hi ${first.contact}, your delivery is complete at ${first.done} by ${DRIVER}.`, 'Product delivered: See the manifest for details.'] },
				{ who: 'pickup', at: 0.03, time: first.done, subject: '✓ Stop 4 of 6 delivered', lines: [`${first.name} · ${first.area} · ${first.done}.`] },
				{ who: 'dropoff', chip: chip(second), at: 0.1, time: '11:06 AM', subject: "You're next · ETA window", lines: [`Hi ${second.contact}, ${DRIVER} just completed the ${first.name} stop. You're next.`, `ETA window: ${second.window} (on time).`] },
				{ who: 'dropoff', chip: chip(second), at: 0.59, time: second.done, subject: '✓ Delivered', lines: [`Hi ${second.contact}, your delivery is complete at ${second.done} by ${DRIVER}.`, 'Product delivered: See the manifest for details.'] },
				{ who: 'pickup', at: 0.61, time: second.done, subject: '✓ Stop 5 of 6 delivered', lines: [`${second.name} · ${second.area} · ${second.done}.`] },
				{ who: 'dropoff', chip: chip(third), at: 0.64, time: '11:29 AM', subject: "You're next · ETA window", lines: [`Hi ${third.contact}, ${DRIVER} just completed the ${second.name} stop. You're next.`, `ETA window: ${third.window} (on time).`] },
				{ who: 'dropoff', chip: chip(third), at: 0.88, time: '11:43 AM', subject: '5 minutes away', lines: [`Hi ${third.contact}, ${DRIVER} is about 5 minutes away.`, 'Please have intake ready.'] },
				{ who: 'dropoff', chip: chip(third), at: 0.97, time: third.done, subject: '✓ Delivered', lines: [`Hi ${third.contact}, your delivery is complete at ${third.done} by ${DRIVER}.`, 'Product delivered: See the manifest for details.'] },
				{ who: 'pickup', at: 0.98, time: third.done, subject: '✓ All stops delivered', lines: ['Hi Dana, all 3 Minneapolis deliveries on today\'s route are complete.', `1. ${first.name} · ${first.done}`, `2. ${second.name} · ${second.done}`, `3. ${third.name} · ${third.done}`] },
			],
		},
	};
};

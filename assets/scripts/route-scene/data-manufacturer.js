/**
 * Route map variant `manufacturer` (hand-written; data.js is generated from the comps): a
 * manufacturer in St. Cloud with three dispensary drop-offs on the Zone 1 Central route. Same
 * shape as data.js: (prog) => { hud: [[status, line, dot colour, time] per step], texts: { step:
 * [{ who, at, time, subject, lines, chip? }] } }. `chip` names the drop-off contact an email went
 * to; each dispensary gets its own ETA window.
 */

const DRIVER = 'Marcus';
const PICK = 'Granite City Infusions';
const STOPS = [
	{ name: 'Mill City Cannabis Co.', city: 'Minneapolis', contact: 'Jordan', window: '9:30–10:15 AM', done: '9:55 AM', at: 0.71 },
	{ name: 'Capitol Green Dispensary', city: 'St. Paul', contact: 'Priya', window: '10:15–10:45 AM', done: '10:28 AM', at: 0.815 },
	{ name: 'St. Croix Valley Cannabis', city: 'Stillwater', contact: 'Sam', window: '10:50–11:30 AM', done: '11:08 AM', at: 1 },
];
const chip = (stop) => `${stop.contact} · ${stop.city}`;

const color = (name) => `var(--wp--preset--color--${name})`;

// Clock time on the route: pickup done at 8:40, last drop-off at 11:05.
const clock = (prog) => {
	const minutes = 8 * 60 + 40 + Math.round(prog * 145);
	return `TUE ${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
};

const nextStop = (prog) => STOPS.find((stop) => prog < stop.at) || STOPS[STOPS.length - 1];

export const manufacturer = (prog) => {
	const [mpls, stp, stw] = STOPS;

	return {
		hud: [
			['Regional routes', 'Five regions · five zones', color('gray-500'), ''],
			['Zone 1', 'St. Cloud · Central route', color('gold-500'), 'MON 09:00'],
			['Stops booked', '3 drop-offs booked before 3pm · all facilities notified', color('gold-500'), 'MON 11:02'],
			['Route departed', 'Stop 1 of 4 · ETA windows sent', color('lime-700'), 'TUE 07:40'],
			['At pickup', 'Manifests verified · 1 of 4 stops complete', color('lime-700'), 'TUE 08:31'],
			['On route', `Next stop: ${nextStop(prog).city} · GPS live`, color('green-500'), clock(prog)],
			['Delivered', 'Stop 4 of 4 · every contact emailed', color('green-800'), 'TUE 11:08'],
			['Documented', 'Route record stored in CRM', color('green-800'), 'TUE 11:10'],
		],
		texts: {
			3: [
				{ who: 'both', at: 0, time: '7:30 AM', subject: 'Route dispatched', lines: [`Going Green Transport dispatched the Central route to ${DRIVER} (4 stops).`, `1. Pickup: ${PICK} · St. Cloud`, `2. Dropoff: ${mpls.name} · Minneapolis`, `3. Dropoff: ${stp.name} · St. Paul`, `4. Dropoff: ${stw.name} · Stillwater`] },
				{ who: 'pickup', at: 0.25, time: '7:40 AM', subject: 'On the way · ETA window', lines: [`Hi Dana, ${DRIVER} is on the way to ${PICK}.`, 'Pickup ETA window: 8:15–8:45 AM.'] },
				{ who: 'dropoff', chip: chip(mpls), at: 0.4, time: '7:40 AM', subject: 'Your ETA window', lines: [`Hi ${mpls.contact}, your delivery from ${PICK} is on today's Central route.`, `ETA window: ${mpls.window}.`] },
				{ who: 'pickup', at: 0.55, time: '8:10 AM', subject: '15 minutes away', lines: [`Hi Dana, ${DRIVER} is about 15 minutes from ${PICK}.`] },
				{ who: 'pickup', at: 0.8, time: '8:20 AM', subject: '5 minutes away', lines: [`Hi Dana, ${DRIVER} is about 5 minutes away.`, 'Please have product staged for handoff.'] },
			],
			4: [
				{ who: 'both', at: 0.25, time: '8:31 AM', subject: '✓ Pickup completed', lines: [`${DRIVER} completed the pickup at St. Cloud, MN at 8:31 AM.`, 'Manifests verified for 3 drop-offs.', 'Progress: 1 of 4 stops complete.'] },
				{ who: 'dropoff', chip: chip(stp), at: 0.55, time: '8:33 AM', subject: 'Your ETA window', lines: [`Hi ${stp.contact}, your delivery from ${PICK} is on the way.`, `ETA window: ${stp.window}.`] },
				{ who: 'dropoff', chip: chip(stw), at: 0.8, time: '8:33 AM', subject: 'Your ETA window', lines: [`Hi ${stw.contact}, your delivery from ${PICK} is on the way.`, `ETA window: ${stw.window}.`] },
			],
			5: [
				{ who: 'dropoff', chip: chip(mpls), at: 0.55, time: '9:40 AM', subject: '15 minutes away', lines: [`Hi ${mpls.contact}, ${DRIVER} is about 15 minutes from ${mpls.name}.`, `Inside your ETA window (${mpls.window}).`] },
				{ who: 'dropoff', chip: chip(mpls), at: 0.71, time: mpls.done, subject: '✓ Delivered', lines: [`Hi ${mpls.contact}, your delivery is complete at ${mpls.done} by ${DRIVER}.`, 'Product delivered: See the manifest for details.'] },
				{ who: 'pickup', at: 0.72, time: mpls.done, subject: '✓ Stop 2 of 4 delivered', lines: [`${mpls.name} · Minneapolis · ${mpls.done}.`] },
				{ who: 'dropoff', chip: chip(stp), at: 0.75, time: '9:58 AM', subject: "You're next · ETA window", lines: [`Hi ${stp.contact}, ${DRIVER} just completed the Minneapolis stop. You're next.`, `ETA window: ${stp.window} (on time).`] },
				{ who: 'dropoff', chip: chip(stp), at: 0.815, time: stp.done, subject: '✓ Delivered', lines: [`Hi ${stp.contact}, your delivery is complete at ${stp.done} by ${DRIVER}.`, 'Product delivered: See the manifest for details.'] },
				{ who: 'pickup', at: 0.825, time: stp.done, subject: '✓ Stop 3 of 4 delivered', lines: [`${stp.name} · St. Paul · ${stp.done}.`] },
				{ who: 'dropoff', chip: chip(stw), at: 0.9, time: '10:50 AM', subject: '15 minutes away', lines: [`Hi ${stw.contact}, ${DRIVER} is about 15 minutes from ${stw.name}.`, `Inside your ETA window (${stw.window}).`] },
			],
			6: [
				{ who: 'dropoff', chip: chip(stw), at: 0, time: '11:05 AM', subject: 'Arrived', lines: [`${DRIVER} has arrived at ${stw.name}.`] },
				{ who: 'dropoff', chip: chip(stw), at: 0.4, time: stw.done, subject: '✓ Delivered', lines: [`Hi ${stw.contact}, your delivery is complete at ${stw.done} by ${DRIVER}.`, 'Product delivered: See the manifest for details.'] },
				{ who: 'pickup', at: 0.4, time: stw.done, subject: '✓ All stops delivered', lines: ['Hi Dana, all 3 deliveries on today\'s route are complete.', `1. ${mpls.name} · ${mpls.done}`, `2. ${stp.name} · ${stp.done}`, `3. ${stw.name} · ${stw.done}`] },
			],
		},
	};
};

/**
 * Route map variant `regional` (the lab run): hand-written override of the generated data.js
 * entry. Same Duluth cultivator -> Twin Cities testing lab route; stops 2 and 3 are pickups from
 * two other manufacturers on the route (Rick in Hinckley, James in North Branch), each with an
 * ETA window, and the emails fire when the van reaches each stop. Same shape as data.js; `chip`
 * names the contact an email went to.
 */

const DRIVER = 'Marcus';
const PICK = 'Northshore Cultivation Co.';
const LAB = 'Twin Cities Analytical Lab';
const LAB_ADDR = '845 Industrial Blvd NE, Minneapolis, MN 55413';
const STOP2 = { name: 'Grindstone River Edibles', city: 'Hinckley', contact: 'Rick', window: '9:15–9:45 AM', done: '9:38 AM' };
const STOP3 = { name: 'Sunrise River Manufacturing', city: 'North Branch', contact: 'James', window: '10:00–10:30 AM', done: '10:14 AM' };
const chip = (stop) => `${stop.contact} · ${stop.name}`;

// Route progress at each pickup (scene.js route points 3 and 5).
const AT = { hinckley: 0.4916, northBranch: 0.7258 };

export const regional = (prog) => {
	let nextStop = `${LAB} · Minneapolis`;
	if (prog < AT.hinckley) {
		nextStop = `${STOP2.city} pickup`;
	} else if (prog < AT.northBranch) {
		nextStop = `${STOP3.city} pickup`;
	}

	return {
		hud: [
			['Regional routes', 'Five regions · five zones', 'var(--wp--preset--color--gray-500)', ''],
			['Zone 2', 'Duluth · Northeast route', 'var(--wp--preset--color--gold-500)', 'MON 09:00'],
			['Stop booked', 'Booked before 3pm · both facilities notified', 'var(--wp--preset--color--gold-500)', 'MON 11:02'],
			['Route departed', 'Stop 1 of 4 · pickup contact emailed', 'var(--wp--preset--color--lime-700)', 'TUE 07:40'],
			['At stop 1', 'Manifest verified · 1 of 4 stops complete', 'var(--wp--preset--color--lime-700)', 'TUE 08:31'],
			['On route', `Next stop: ${nextStop} · GPS live`, 'var(--wp--preset--color--green-500)', 'TUE ' + String(9 + Math.floor(prog * 2)).padStart(2, '0') + ':' + String(Math.floor((prog * 120) % 60)).padStart(2, '0')],
			['Delivered', 'Stop 4 of 4 · both contacts emailed', 'var(--wp--preset--color--green-800)', 'TUE 11:18'],
			['Documented', 'Route record stored in CRM', 'var(--wp--preset--color--green-800)', 'TUE 11:20'],
		],
		texts: {
			3: [
				{ who: 'both', at: 0, time: '7:30 AM', subject: 'Route dispatched', lines: ['Going Green Transport dispatched the Northeast route to ' + DRIVER + ' (4 stops).', '1. Pickup: ' + PICK + ' · Duluth', `2. Pickup: ${STOP2.name} · ${STOP2.city}`, `3. Pickup: ${STOP3.name} · ${STOP3.city}`, '4. Dropoff: ' + LAB + ' · Minneapolis'] },
				{ who: 'pickup', at: 0.3, time: '7:40 AM', subject: 'On the way', lines: ['Hi Mary, ' + DRIVER + ' is on the way. You are stop 1 of 4 on today\'s Northeast route.'] },
				{ who: 'pickup', at: 0.55, time: '8:10 AM', subject: '15 minutes away', lines: ['Hi Mary, ' + DRIVER + ' is about 15 minutes from ' + PICK + '.'] },
				{ who: 'pickup', at: 0.8, time: '8:20 AM', subject: '5 minutes away', lines: ['Hi Mary, ' + DRIVER + ' is about 5 minutes away.', 'Please have product staged for handoff.'] },
			],
			4: [
				{ who: 'both', at: 0.3, time: '8:31 AM', subject: '✓ Pickup completed', lines: [DRIVER + ' completed the pickup at Duluth, MN at 8:31 AM.', 'Progress: 1 of 4 stops complete.'] },
				{ who: 'pickup', chip: chip(STOP2), at: 0.7, time: '8:33 AM', subject: 'Your ETA window', lines: [`Hi ${STOP2.contact}, ${DRIVER} is on today's Northeast route. You are stop 2 of 4.`, `Pickup ETA window: ${STOP2.window}.`] },
			],
			5: [
				{ who: 'pickup', chip: chip(STOP2), at: 0.42, time: '9:30 AM', subject: '5 minutes away', lines: [`Hi ${STOP2.contact}, ${DRIVER} is about 5 minutes away.`, 'Please have product staged for handoff.'] },
				{ who: 'pickup', chip: chip(STOP2), at: 0.5, time: STOP2.done, subject: '✓ Pickup completed', lines: [`${DRIVER} completed the pickup at ${STOP2.city}, MN at ${STOP2.done}.`, 'Progress: 2 of 4 stops complete.'] },
				{ who: 'dropoff', at: 0.53, time: '9:42 AM', subject: 'Route update', lines: ['Hi Jane, ' + DRIVER + ' completed the Hinckley pickup. Your delivery is on schedule.'] },
				{ who: 'pickup', chip: chip(STOP3), at: 0.56, time: '9:43 AM', subject: "You're next · ETA window", lines: [`Hi ${STOP3.contact}, ${DRIVER} just left ${STOP2.city}. You're next.`, `Pickup ETA window: ${STOP3.window}.`] },
				{ who: 'pickup', chip: chip(STOP3), at: 0.73, time: STOP3.done, subject: '✓ Pickup completed', lines: [`${DRIVER} completed the pickup at ${STOP3.city}, MN at ${STOP3.done}.`, 'Progress: 3 of 4 stops complete.'] },
				{ who: 'dropoff', at: 0.85, time: '10:57 AM', subject: '15 minutes away', lines: ['Hi Jane, ' + DRIVER + ' is about 15 minutes from ' + LAB + '.'] },
				{ who: 'dropoff', at: 0.95, time: '11:07 AM', subject: '5 minutes away', lines: ['Hi Jane, ' + DRIVER + ' is about 5 minutes away.', 'Please have intake ready.'] },
			],
			6: [
				{ who: 'both', at: 0, time: '11:12 AM', subject: 'Arrived', lines: [DRIVER + ' has arrived at ' + LAB + '.', 'Address: ' + LAB_ADDR] },
				{ who: 'dropoff', at: 0.4, time: '11:18 AM', subject: '✓ Delivered', lines: ['Hi Jane, confirming the delivery below is complete.', 'Your delivery from ' + PICK + ' is complete at 11:18 AM by ' + DRIVER + '.', 'Address: ' + LAB_ADDR, 'Product delivered: See the manifest for details.'] },
				{ who: 'pickup', at: 0.4, time: '11:18 AM', subject: '✓ Delivered', lines: ['Hi Mary, confirming the delivery below is complete.', 'Your delivery to ' + LAB + ' is complete at 11:18 AM by ' + DRIVER + '.', 'Address: ' + LAB_ADDR, 'Product delivered: See the manifest for details.'] },
			],
		},
	};
};

<?php

/**
 * Location hero photos from Wikimedia Commons: city slug => [landmark, downtown].
 *
 * Alt text is "<Subject> in <City>, Minnesota". Every photo was checked in the 477x751 center crop.
 *
 * @package GoingGreenTransportBlockTheme
 */

// phpcs:disable

$ggtRows = [
	['albert-lea', 'Albert Lea', 'Albert Lea Lake', 'Albert Lea Lake in Spring - Myre-Big Island State Park, Minnesota (47926136481).jpg', 'CC BY 2.0', 'Tony Webster', 'Downtown Broadway', 'AlbertLeaMNdowntown.JPG', 'CC BY-SA 3.0', 'Jonathunder'],
	['austin', 'Austin', 'SPAM Museum', 'Signage, Spam Museum, Austin, Minnesota.jpg', 'CC BY-SA 4.0', 'Myotus', 'Main Street', 'Downtown Austin MN looking north.jpg', 'CC BY-SA 4.0', 'Darb02'],
	['bemidji', 'Bemidji', 'Paul Bunyan and Babe the Blue Ox', 'PaulBunyanandBabeBemidjiMN.jpg', 'CC BY-SA 3.0', 'Tastocke', 'Beltrami Avenue', 'Bemidji, Minnesota-02-Businesses on Beltrami Avenue.jpg', 'CC BY-SA 4.0', 'Myotus'],
	['brainerd', 'Brainerd', 'Historic water tower', 'City of Brainerd, Minnesota - Water Tower (39818204500).jpg', 'CC BY 2.0', 'Tony Webster', 'Parker Building, downtown', 'Parker Bldg 2.jpg', 'CC BY-SA 3.0', 'Elkman'],
	['duluth', 'Duluth', 'Aerial Lift Bridge', 'AerialLiftBridgeNight.jpg', 'CC BY-SA 3.0', 'Jonathunder', 'Downtown Duluth', 'Downtown Duluth MN, Aug 2023 1.jpg', 'CC0', 'Thomson200'],
	['east-grand-forks', 'East Grand Forks', 'Sorlie Bridge over the Red River', 'Sorlie Memorial Bridge - Red River State Recreation Area (37074916543).jpg', 'CC BY-SA 2.0', 'Tony Webster', 'Downtown businesses along the flood wall', 'East Grand Forks, Minnesota - Flood Wall and Businesses (23942476638).jpg', 'CC BY-SA 2.0', 'Tony Webster'],
	['faribault', 'Faribault', 'Cathedral of Our Merciful Saviour', 'Neo-Gothic - Faribault, MN - Cathedral of our Merciful Saviour (1).jpg', 'CC BY 4.0', 'Bmzuckerman', 'Central Avenue', 'Central Avenue - Downtown Faribault, Minnesota - Historic Brick Buildings (28057607009).jpg', 'CC BY-SA 2.0', 'Tony Webster'],
	['fergus-falls', 'Fergus Falls', 'Otter Tail River falls', 'Otter Tail River dam - Fergus Falls 02.jpg', 'CC BY-SA 4.0', 'Farragutful', 'Fergus Theatre on Lincoln Avenue', 'Fergus Theatre.jpg', 'CC BY-SA 4.0', 'Farragutful'],
	['grand-rapids', 'Grand Rapids', "Judy Garland's birthplace", 'Judy Garland Museum-birthplace.jpg', 'CC BY-SA 4.0', 'Myotus', '3rd Street', 'Businesses on 3rd Street, Grand Rapids, MN.jpg', 'CC BY 4.0', 'Myotus'],
	['hibbing', 'Hibbing', 'Hull-Rust-Mahoning Mine', '2025-01-18 Hull-Rust-Mahoning Open Pit Iron Mine, view from visitor center.jpg', 'CC BY 4.0', 'Minnesota Historical Society Heritage Preservation Department', 'Howard Street and the Androy Hotel', 'Hibbing Minnesota - Fall Colors.jpg', 'CC BY 2.0', 'Tony Webster'],
	['hutchinson', 'Hutchinson', 'Rocket Hill Park', 'Rocket Hill Park - Hutchinson, Minnesota, United States.jpg', 'CC BY-SA 2.0', 'davef3138', 'Main Street', 'Main Street looking North toward 2nd Avenue, Hutchinson, MN.jpg', 'CC BY 4.0', 'Myotus'],
	['mankato', 'Mankato', 'Blue Earth County Courthouse', 'Blue Earth County Courthouse 2.jpg', 'CC0', 'Wikideas1', 'Downtown Mankato', 'Mankato, Minnesota downtown.jpg', 'CC BY 4.0', 'Quintin Soloviev'],
	['marshall', 'Marshall', 'Redwood River', 'Redwood River Marshall.jpg', 'CC BY-SA 2.5', 'Tim Kiser', 'Main Street', 'Downtown Marshall, Minnesota (37860713351).jpg', 'CC BY-SA 2.0', 'Tony Webster'],
	['minneapolis', 'Minneapolis', 'Skyline from the Stone Arch Bridge', 'Minneapolis Skyline from Stone Arch Bridge, Mill District, Minneapolis, MN - 51781022013.jpg', 'CC BY-SA 2.0', 'w_lemay', 'Nicollet Mall', 'Nicollet Mall looking north at 8th Street in 2024.jpg', 'CC BY 4.0', 'Eóin'],
	['moorhead', 'Moorhead', 'Hopperstad Stave Church', 'Hopperstad Stave Church Replica, Hjemkomst Center, Moorhead, Minnesota - 42149273130.jpg', 'CC BY-SA 2.0', 'Ken Lund', 'Downtown Moorhead', 'MoorheadMN Downtown.jpg', 'CC BY-SA 2.0', 'Fargo-Moorhead CVB'],
	['new-ulm', 'New Ulm', 'Hermann Heights Monument', 'Hermann Heights Monument, New Ulm 2024-10-05.jpg', 'CC BY 4.0', 'Schwerdf', 'Minnesota Street', 'New Ulm, Minnesota street scene.jpg', 'CC BY 2.0', 'Greg Gjerdingen'],
	['owatonna', 'Owatonna', "National Farmers' Bank by Louis Sullivan", "Louis H. Sullivan - National Farmer's Bank (C), Owatonna, MN.jpg", 'CC BY 4.0', 'Bmzuckerman', 'Downtown Owatonna', 'Owatonna, Minnesota 5.jpg', 'CC BY-SA 3.0', 'Jon Platek'],
	['red-wing', 'Red Wing', 'Barn Bluff', 'Barn Bluff from Waterfall Valley.jpg', 'CC BY-SA 3.0', 'JJ Harrison', 'Main Street', 'Main Street, Red Wing, Minnesota (28248316542).jpg', 'CC BY-SA 2.0', 'Tony Webster'],
	['rochester', 'Rochester', 'Mayo Clinic Plummer Building', 'Plummer Building, Mayo Clinic, 2nd Avenue SW and 2nd Street SW, Rochester, MN - 54368240367.jpg', 'CC BY-SA 2.0', 'w_lemay', 'Downtown Rochester', 'Downtown Rochester Minnesota (53372463515).jpg', 'CC BY 2.0', 'Tony Webster'],
	['st-cloud', 'St. Cloud', 'Cathedral of Saint Mary', 'Cathedral of Saint Mary - St. Cloud, Minnesota 04.jpg', 'CC BY-SA 4.0', 'Farragutful', 'Downtown St. Cloud', 'Downtown St. Cloud, MN (2).jpg', 'CC BY-SA 4.0', 'Ryan Herrboldt'],
	['st-paul', 'St. Paul', 'Minnesota State Capitol', 'Minnesota State Capitol - 54147672485.jpg', 'CC BY 2.0', 'xiquinhosilva', 'Rice Park and Landmark Center', 'Saint Paul April 2025 16 (Rice Park and Landmark Center).jpg', 'CC BY-SA 4.0', 'Michael Barera'],
	['stillwater', 'Stillwater', 'Stillwater Lift Bridge', 'Stillwater Lift Bridge-b.png', 'CC BY-SA 4.0', 'John Davis', 'Main Street', 'Washington county MN IMG 1704 stillwater.JPG', 'CC BY-SA 3.0', 'Bjoertvedt'],
	['winona', 'Winona', 'Sugar Loaf bluff', 'Winona YMCA with Sugar Loaf in the background.jpg', 'CC0', 'Wikideas1', 'Downtown commercial district', 'Winona Commercial HD 04.jpg', 'CC BY-SA 4.0', 'McGhiever'],
	['worthington', 'Worthington', 'Nobles County Pioneer Village church', 'Nobles County Pioneer Village-Church.jpg', 'CC BY-SA 4.0', 'Myotus', 'Downtown Worthington', 'Worthington Minnesota.jpg', 'CC BY-SA 2.5', 'Tim Kiser'],
];

$ggtPhotos = [];

foreach ($ggtRows as [$slug, $city, $landmark, $landmarkFile, $landmarkLicense, $landmarkAuthor, $downtown, $downtownFile, $downtownLicense, $downtownAuthor]) {
	$ggtPhotos[$slug] = [
		'landmark' => [
			'alt' => "{$landmark} in {$city}, Minnesota",
			'file' => $landmarkFile,
			'license' => $landmarkLicense,
			'author' => $landmarkAuthor,
		],
		'downtown' => [
			'alt' => "{$downtown} in {$city}, Minnesota",
			'file' => $downtownFile,
			'license' => $downtownLicense,
			'author' => $downtownAuthor,
		],
	];
}

return $ggtPhotos;

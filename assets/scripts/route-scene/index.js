/**
 * How It Works route map: enhances the server-rendered map (RouteScene.php) into the animated
 * scene. Without JavaScript the static map stays.
 */
export const initRouteScenes = async () => {
	const scenes = document.querySelectorAll('.ggt-route-scene[data-map-base]');

	if (!scenes.length) {
		return;
	}

	const { RouteScene } = await import('./scene');

	scenes.forEach((el) => new RouteScene(el).init());
};

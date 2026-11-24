/**
 * This is the main entry point for project scripts used for the `WordPress frontend screen`.
 *
 * Usage: `WordPress frontend screen`.
 */

import domReady from '@wordpress/dom-ready';
import { initRouteScenes } from './route-scene';
import { initProcessReveal } from './process-reveal';
import { initColStart } from './col-start';
import { initFilterFade } from './filter-fade';
import { initHeroV5 } from './hero-v5';

domReady(initRouteScenes);
domReady(initProcessReveal);
domReady(initColStart);
domReady(initFilterFade);
domReady(initHeroV5);

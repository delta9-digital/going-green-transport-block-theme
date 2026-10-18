/**
 * This is the main entry point for project scripts used for the `WordPress frontend screen`.
 *
 * Usage: `WordPress frontend screen`.
 */

import domReady from '@wordpress/dom-ready';
import { initRouteScenes } from './route-scene';

domReady(initRouteScenes);

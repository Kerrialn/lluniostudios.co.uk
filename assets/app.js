import './bootstrap.js';
import './styles/app.css';
import { initFlowbite } from 'flowbite';

/*
 * Vite entrypoint for Llunio Studios.
 * Tailwind v4 + Flowbite + Stimulus (via vite-plugin-symfony) + Swup page transitions.
 */

// Flowbite auto-inits on DOMContentLoaded, but Swup swaps the DOM without a full
// reload, so re-initialise interactive components after each page transition.
const reinit = () => initFlowbite();
document.addEventListener('DOMContentLoaded', reinit);
document.addEventListener('swup:contentReplaced', reinit);
document.addEventListener('swup:content:replace', reinit);

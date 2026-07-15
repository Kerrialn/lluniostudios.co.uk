import { startStimulusApp, registerControllers } from 'vite-plugin-symfony/stimulus/helpers';

const app = startStimulusApp();

// Register local controllers in assets/controllers/*_controller.js
// (startStimulusApp only wires the third-party controllers from controllers.json).
// NB: the glob must be lazy (no `eager`) so each value is a `() => import()`
// factory — registerControllers wraps those as lazy Stimulus controllers.
// With `eager: true` the values are module namespaces, which registerControllers
// silently skips, leaving every local controller unregistered.
registerControllers(
    app,
    import.meta.glob('./controllers/*_controller.js'),
);

export { app };

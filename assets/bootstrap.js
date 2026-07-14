import { startStimulusApp, registerControllers } from 'vite-plugin-symfony/stimulus/helpers';

const app = startStimulusApp();

// Register local controllers in assets/controllers/*_controller.js
// (startStimulusApp only wires the third-party controllers from controllers.json).
registerControllers(
    app,
    import.meta.glob('./controllers/*_controller.js', {
        eager: true,
    }),
);

export { app };

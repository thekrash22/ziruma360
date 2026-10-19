import { route as routeFn, Router } from 'ziggy-js';

declare global {
    interface Window {
        route: typeof routeFn;
        Ziggy: Router;
    }
}

export {};

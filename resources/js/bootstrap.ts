import axios from 'axios';
import route from 'ziggy-js';

declare global {
    interface Window {
        route: typeof route;
    }
}

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

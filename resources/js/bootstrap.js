import 'bootstrap';

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? window.location.protocol.replace(':', '') ?? 'http';
    const reverbHost = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
    const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? (reverbScheme === 'https' ? 443 : 8080));

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: csrfToken ? {
                'X-CSRF-TOKEN': csrfToken,
            } : {},
        },
    });
}

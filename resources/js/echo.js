import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const key = import.meta.env.VITE_REVERB_APP_KEY;
const browserPort = window.location.port || (window.location.protocol === 'https:' ? 443 : 80);

window.Pusher = Pusher;
window.Echo = key
    ? new Echo({
          broadcaster: 'reverb',
          key,
          wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
          wsPort: Number(import.meta.env.VITE_REVERB_PORT || browserPort),
          wssPort: Number(import.meta.env.VITE_REVERB_PORT || browserPort),
          forceTLS: (import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.slice(0, -1)) === 'https',
          enabledTransports: ['ws', 'wss'],
      })
    : null;

export default window.Echo;

import Chart from 'chart.js/auto';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Chart = Chart;
window.Pusher = Pusher;

// Broadcaster is selected per environment: Reverb for local docker-compose,
// Pusher on Render (set VITE_BROADCASTER=pusher there).
const broadcaster = import.meta.env.VITE_BROADCASTER ?? 'reverb';

// Guard Echo setup so a misconfigured broadcaster never breaks the rest of
// the UI (dropdowns, sidebar) — and log enough to diagnose baked-in config.
try {
    if (broadcaster === 'pusher' && !import.meta.env.VITE_PUSHER_APP_KEY) {
        throw new Error('VITE_PUSHER_APP_KEY missing from build');
    }
    if (broadcaster === 'reverb' && !import.meta.env.VITE_REVERB_APP_KEY) {
        throw new Error('VITE_REVERB_APP_KEY missing from build');
    }

    window.Echo = new Echo(
        broadcaster === 'pusher'
            ? {
                  broadcaster: 'pusher',
                  key: import.meta.env.VITE_PUSHER_APP_KEY,
                  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
                  forceTLS: true,
              }
            : {
                  broadcaster: 'reverb',
                  key: import.meta.env.VITE_REVERB_APP_KEY,
                  wsHost: import.meta.env.VITE_REVERB_HOST,
                  wsPort: import.meta.env.VITE_REVERB_PORT,
                  wssPort: import.meta.env.VITE_REVERB_PORT,
                  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                  enabledTransports: ['ws', 'wss'],
                  disableStats: true,
              },
    );

    console.info(`[Echo] broadcaster=${broadcaster}`);
    window.Echo.connector.pusher.connection.bind('state_change', ({ current }) => {
        console.info(`[Echo] connection: ${current}`);
    });
} catch (error) {
    console.error('[Echo] real-time disabled:', error.message);
}

// ========== Live unread-notification badge (navbar bell) ==========
// Listens on the user's private channel and bumps the badge whenever a
// notification is created (e.g. someone answered their question).
const notifBell = document.querySelector('[data-notif-bell]');
if (window.Echo && notifBell) {
    const badge = notifBell.querySelector('[data-notif-badge]');
    window.Echo.private(`user.${notifBell.dataset.userId}`).listen(
        'NotificationCreated',
        () => {
            if (!badge) return;
            const count = (parseInt(badge.dataset.count, 10) || 0) + 1;
            badge.dataset.count = count;
            badge.textContent = Math.min(count, 99);
            badge.style.display = 'flex';
        },
    );
}

document.addEventListener("click", (event) => {
    document.querySelectorAll("[data-user-menu]").forEach((menu) => {
        const trigger = menu.querySelector("[data-menu-toggle]");
        const clickedInside = menu.contains(event.target);

        if (trigger && trigger.contains(event.target)) {
            menu.classList.toggle("is-open");
            return;
        }

        if (!clickedInside) {
            menu.classList.remove("is-open");
        }
    });
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        document.querySelectorAll("[data-user-menu]").forEach((menu) => {
            menu.classList.remove("is-open");
        });
    }
});

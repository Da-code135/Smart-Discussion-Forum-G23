import Chart from 'chart.js/auto';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Chart = Chart;
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});

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

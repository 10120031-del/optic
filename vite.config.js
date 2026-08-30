import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // face-scan.js is a separate entry so the MediaPipe bundle only
            // loads on the AI Face Match page, not site-wide.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/face-scan.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '127.0.0.1',
        // Ensures the Vite dev server registers itself (in public/hot) as
        // 127.0.0.1 rather than the IPv6 [::1] loopback, which some Windows
        // setups fail to reach from the browser — the classic cause of a
        // page that loads but has no CSS/JS and no visible console error.
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

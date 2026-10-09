import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/profile-avatar.js', 'resources/js/message-updates.js'],
            refresh: true,
            fonts: [
                local('Instrument Sans', {
                    variants: [400, 500, 600].map(weight => ({
                        src: `resources/fonts/instrument-sans/instrument-sans-latin-${weight}-normal.woff2`,
                        weight,
                    })),
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

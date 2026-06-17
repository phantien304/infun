import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Frontend (area=web) — Tailwind v4 build, theme + component
                // bridge cho coexistence với Bootstrap legacy
                // (xem resources/web/css/app.css).
                'resources/web/css/app.css',
                // CMS (area=cms) — React + antd + Tailwind.
                'resources/cms/js/app.jsx',
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    resolve: {
        alias: {
            // `@` = entry CMS React. Sau reorganize area-first
            // (resources/js/cms → resources/cms/js).
            '@': path.resolve(__dirname, 'resources/cms/js'),
        },
    },
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import fs from 'fs';

const themesDir = path.resolve(__dirname, 'resources/themes');
const themeEntries = fs.existsSync(themesDir)
    ? fs.readdirSync(themesDir, { withFileTypes: true })
        .filter((d) => d.isDirectory())
        .map((d) => `resources/themes/${d.name}/css/app.css`)
        .filter((p) => fs.existsSync(path.resolve(__dirname, p)))
    : [];

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/web/css/app.css',
                ...themeEntries,
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    resolve: {
        alias: {
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5174,
        strictPort: true,
        hmr: {
            host: 'localhost',
            port: 5174,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

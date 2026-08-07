import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import fs from 'fs';

// ── Entry CSS của từng theme storefront ────────────────────────────────────
// Mỗi theme trong resources/themes/<tên>/css/app.css thành một entry riêng,
// build ra một file CSS riêng. Blade chọn entry nào qua
// ThemeManager::viteEntry() (xem resources/web/views/share/head.blade.php).
//
// Quét thư mục thay vì liệt kê tay: thêm theme mới chỉ cần tạo folder, khỏi
// sửa file này rồi quên.
//
// LƯU Ý Tailwind v4: nó dò class bằng cách quét file nguồn từ chính entry CSS
// (@source / auto-detect). Mỗi theme có entry riêng nên chỉ quét blade của
// chính nó + base — đó là lý do entry theme phải `@import` app.css của base
// chứ đừng copy nội dung, nếu không class dùng ở view base sẽ bị thiếu.
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
                // Frontend (area=web) — Tailwind v4 build, theme + component
                // bridge cho coexistence với Bootstrap legacy
                // (xem resources/web/css/app.css).
                'resources/web/css/app.css',
                // CMS (area=cms) — React + antd + Tailwind.
                'resources/cms/js/app.jsx',
                // Theme storefront (0..n) — xem chú thích ở đầu file.
                ...themeEntries,
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

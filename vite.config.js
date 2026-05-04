import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import tailwind from '@tailwindcss/vite';

const isReplit = !!process.env.REPLIT_DEV_DOMAIN;
const replitDomain = process.env.REPLIT_DEV_DOMAIN;

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: isReplit ? {
            host: replitDomain,
            protocol: 'wss',
            clientPort: 443,
        } : {
            host: 'localhost',
        },
        allowedHosts: true,
        watch: {
            ignored: ['**/node_modules/**', '**/.cache/**', '**/storage/**', '**/vendor/**'],
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwind(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});

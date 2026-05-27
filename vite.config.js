import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

function detectTlsHostFromAppUrl(appUrl) {
    try {
        const url = new URL(appUrl);
        return url.protocol === 'https:' ? url.hostname : null;
    } catch {
        return null;
    }
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: 'resources/js/app.jsx',
                refresh: true,
                detectTls: detectTlsHostFromAppUrl(env.APP_URL),
            }),
            react(),
            tailwindcss(),
        ],
    };
});

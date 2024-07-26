import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'path';
export default defineConfig({
    server: {
        host: "callingfeature.cc",
        hmr: {
            host: "callingfeature.cc"
        },
        https: {
            key: "C:/xampp/apache/cert/callingfeature.cc/key.pem",
            cert: "C:/xampp/apache/cert/callingfeature.cc/cert.pem"
        }
    },
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    }
});
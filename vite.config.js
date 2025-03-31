import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import Vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/sass/login.scss',
                'resources/js/auth.js',
                'resources/sass/dashboard.scss',
            ],
            refresh: true,
        }),
        Vue(),
    ],
    resolve: {
        alias: {
            'vue': 'vue/dist/vue.esm-bundler.js',
        },
    },
});

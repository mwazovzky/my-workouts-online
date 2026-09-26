import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        include: ['resources/js/**/*.test.js'],
        coverage: {
            provider: 'v8',
            reportsDirectory: './coverage/frontend',
            reporter: ['text', 'lcov', 'json-summary'],
            all: true,
            include: ['resources/js/**/*.{js,vue}'],
            exclude: [
                'resources/js/**/*.test.js',
                'resources/js/app.js',
                'resources/js/bootstrap.js',
                'resources/js/Pages/**/*.vue',
            ],
        },
    },
});
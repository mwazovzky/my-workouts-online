import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { publicCatalogPlugin } from './src/lib/catalogFiles.js';

export default defineConfig({
  plugins: [vue(), publicCatalogPlugin()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  test: {
    environment: 'happy-dom',
    include: ['src/**/*.test.js'],
    coverage: {
      provider: 'v8',
      reportsDirectory: './coverage',
      reporter: ['text', 'lcov', 'json-summary', 'html'],
      include: ['src/**/*.{js,vue}'],
      exclude: ['src/**/*.test.js', 'src/main.js', 'src/pages/**/*.vue'],
      // Minimum line coverage (%). CI fails below this; raise as coverage improves.
      thresholds: { lines: 77 },
    },
  },
});

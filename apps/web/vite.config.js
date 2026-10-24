import { fileURLToPath, URL } from 'node:url';
import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';
import VueDevTools from 'vite-plugin-vue-devtools';
import { buildRobots, buildSitemap, DEV_SITE_URL, normalizeSiteUrl } from './src/lib/crawl.js';

/**
 * Generates `robots.txt` and `sitemap.xml` for the site origin: emitted into
 * `dist/` by the build and served by the dev server. Also replaces
 * `__SITE_URL__` in `index.html`, for the absolute URLs in its head.
 */
function siteFiles(siteUrl) {
  const files = {
    'robots.txt': { type: 'text/plain; charset=utf-8', body: () => buildRobots(siteUrl) },
    'sitemap.xml': { type: 'application/xml; charset=utf-8', body: () => buildSitemap(siteUrl) },
  };

  return {
    name: 'site-files',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        const file = files[req.url?.split('?')[0].slice(1)];

        if (!file) {
          return next();
        }

        res.setHeader('Content-Type', file.type);
        res.end(file.body());
      });
    },
    // Before Vite's own HTML handling, which would treat the og:image URL as
    // a relative asset path.
    transformIndexHtml: {
      order: 'pre',
      handler: html => html.replaceAll('__SITE_URL__', normalizeSiteUrl(siteUrl)),
    },
    generateBundle() {
      for (const [fileName, file] of Object.entries(files)) {
        this.emitFile({ type: 'asset', fileName, source: file.body() });
      }
    },
  };
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const apiTarget = env.VITE_API_PROXY_TARGET || 'http://localhost:8080';
  // The public origin used in absolute URLs; the web image requires it.
  const siteUrl = env.VITE_PUBLIC_URL || DEV_SITE_URL;

  return {
    plugins: [VueDevTools(), vue(), siteFiles(siteUrl)],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      host: '0.0.0.0',
      port: 5174,
      proxy: {
        '/api': { target: apiTarget, changeOrigin: false },
        '/.well-known': { target: apiTarget, changeOrigin: false },
      },
    },
  };
});

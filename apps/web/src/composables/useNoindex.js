import { onMounted, onUnmounted } from 'vue';

/**
 * Adds `<meta name="robots" content="noindex">` while the calling page is
 * shown, for pages served with status 200 that must not be indexed, such as
 * the client-side not-found page.
 */
export function useNoindex() {
  let meta;

  onMounted(() => {
    meta = document.createElement('meta');
    meta.name = 'robots';
    meta.content = 'noindex';
    document.head.appendChild(meta);
  });

  onUnmounted(() => {
    meta?.remove();
  });
}

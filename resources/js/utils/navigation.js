export function navigateWithLoading(routeName, params, loadingRef) {
  // set loading flag (ref) so caller can show disabled state / text
  if (loadingRef) loadingRef.value = true;

  // small timeout so the UI can update (gives immediate feedback)
  setTimeout(() => {
    window.location.href = route(routeName, params);
  }, 50);
}

export function isBrowserSecureContext(): boolean {
  if (typeof window === 'undefined') return true;

  return window.isSecureContext
    || ['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname);
}

export function secureMediaContextMessage(feature: string): string {
  if (typeof window === 'undefined') return `${feature} requires a secure browser context.`;

  const secureUrl = `https://${window.location.host}${window.location.pathname}${window.location.search}`;

  return `${feature} is blocked on ${window.location.origin}. Open ${secureUrl} and try again.`;
}

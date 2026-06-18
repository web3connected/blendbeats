export const FWDUVP_BASE_PATH = '/media/fwduvp';
export const FWDUVP_CONTENT_PATH = `${FWDUVP_BASE_PATH}/content`;
export const FWDUVP_SCRIPT_PATH = `${FWDUVP_BASE_PATH}/java/FWDUVPlayer.js`;
export const FWDUVP_STYLESHEET_PATH = `${FWDUVP_CONTENT_PATH}/global.css`;

let loaderPromise: Promise<void> | null = null;

function isFWDUVPlayerReady() {
  return typeof window !== 'undefined' && typeof window.FWDUVPlayer === 'function';
}

function ensureStylesheet(href: string) {
  const existing = document.querySelector<HTMLLinkElement>(`link[data-fwduvp-stylesheet="${href}"]`);

  if (existing) return;

  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = href;
  link.dataset.fwduvpStylesheet = href;
  document.head.appendChild(link);
}

function ensureScript(src: string) {
  return new Promise<void>((resolve, reject) => {
    if (isFWDUVPlayerReady()) {
      resolve();
      return;
    }

    const existing = document.querySelector<HTMLScriptElement>(`script[data-fwduvp-script="${src}"]`);

    if (existing) {
      existing.addEventListener('load', () => resolve(), { once: true });
      existing.addEventListener('error', () => reject(new Error(`Unable to load FWDUVPlayer from ${src}`)), { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.dataset.fwduvpScript = src;
    script.addEventListener('load', () => {
      if (isFWDUVPlayerReady()) {
        resolve();
        return;
      }

      reject(new Error('FWDUVPlayer loaded, but the global constructor was not registered.'));
    }, { once: true });
    script.addEventListener('error', () => reject(new Error(`Unable to load FWDUVPlayer from ${src}`)), { once: true });
    document.body.appendChild(script);
  });
}

export function loadFWDUVPlayer() {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('FWDUVPlayer can only load in the browser.'));
  }

  if (isFWDUVPlayerReady()) {
    return Promise.resolve();
  }

  if (!loaderPromise) {
    loaderPromise = new Promise<void>((resolve, reject) => {
      ensureStylesheet(FWDUVP_STYLESHEET_PATH);
      ensureScript(FWDUVP_SCRIPT_PATH).then(resolve).catch((error: Error) => {
        loaderPromise = null;
        reject(error);
      });
    });
  }

  return loaderPromise;
}

import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import CookieBanner from './CookieConsent';

const CONSENT_KEY = 'c2_analytics_consent';

describe('CookieBanner', () => {
  beforeEach(() => {
    localStorage.clear();
    document.cookie = `${CONSENT_KEY}=; Max-Age=0; Path=/; SameSite=Lax`;
  });

  afterEach(() => {
    cleanup();
    localStorage.clear();
    document.cookie = `${CONSENT_KEY}=; Max-Age=0; Path=/; SameSite=Lax`;
  });

  it('persists accepted consent across remounts', async () => {
    const user = userEvent.setup();
    const firstRender = render(<CookieBanner />);

    expect(await screen.findByRole('alertdialog', { name: /cookie consent banner/i })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /accept/i }));

    expect(screen.queryByRole('alertdialog', { name: /cookie consent banner/i })).not.toBeInTheDocument();
    expect(localStorage.getItem(CONSENT_KEY)).toContain('"analytics":true');
    expect(document.cookie).toContain(CONSENT_KEY);

    firstRender.unmount();
    render(<CookieBanner />);

    expect(screen.queryByRole('alertdialog', { name: /cookie consent banner/i })).not.toBeInTheDocument();
  });

  it('falls back to the consent cookie when localStorage is empty', () => {
    document.cookie = `${CONSENT_KEY}=${encodeURIComponent(JSON.stringify({
      analytics: false,
      timestamp: Date.now(),
    }))}; Max-Age=31536000; Path=/; SameSite=Lax`;

    render(<CookieBanner />);

    expect(screen.queryByRole('alertdialog', { name: /cookie consent banner/i })).not.toBeInTheDocument();
    expect(localStorage.getItem(CONSENT_KEY)).toContain('"analytics":false');
  });
});

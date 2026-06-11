import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { HelmetProvider } from '@dr.pogodin/react-helmet';
import { BrowserRouter } from 'react-router-dom';
import { AuthProvider } from './Frontend/components/auth/AuthProvider';
import SiteWrapper from './Frontend/layouts/SiteWrapper';
import HomePage from './Frontend/pages';


const rootElement = document.getElementById('app');

if (! rootElement) {
  throw new Error('Root element not found');
}

createRoot(rootElement).render(
  <StrictMode>
    <HelmetProvider>
      <BrowserRouter>
        <AuthProvider>
          <SiteWrapper>
            <HomePage />
          </SiteWrapper>
        </AuthProvider>
      </BrowserRouter>
    </HelmetProvider>
  </StrictMode>,
);

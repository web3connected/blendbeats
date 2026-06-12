import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { HelmetProvider } from '@dr.pogodin/react-helmet';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './Frontend/components/auth/AuthProvider';
import SiteWrapper from './Frontend/layouts/SiteWrapper';
import AccountPage from './Frontend/pages/auth/AccountPage';
import ForgotPasswordPage from './Frontend/pages/auth/ForgotPasswordPage';
import LoginPage from './Frontend/pages/auth/LoginPage';
import RegisterPage from './Frontend/pages/auth/RegisterPage';
import UserDashboardPage from './Frontend/pages/auth/UserDashboardPage';
import PublicDjProfilePage from './Frontend/pages/dj/PublicDjProfilePage';
import DjsPage from './Frontend/pages/djs';
import GearPage from './Frontend/pages/gear';
import HomePage from './Frontend/pages';
import MerchPage from './Frontend/pages/merch';
import MixesPage from './Frontend/pages/mixes';
import DjLoungePage from './Frontend/pages/social/DjLoungePage';


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
            <Routes>
              <Route path="/" element={<HomePage />} />
              <Route path="/mixes" element={<MixesPage />} />
              <Route path="/merch" element={<MerchPage />} />
              <Route path="/gear" element={<GearPage />} />
              <Route path="/djs" element={<DjsPage />} />
              <Route path="/djs/:handle" element={<PublicDjProfilePage />} />
              <Route path="/dj-lounge" element={<DjLoungePage />} />
              <Route path="/login" element={<LoginPage />} />
              <Route path="/register" element={<RegisterPage />} />
              <Route path="/forgot-password" element={<ForgotPasswordPage />} />
              <Route path="/dashboard" element={<UserDashboardPage />} />
              <Route path="/account" element={<AccountPage />} />
              <Route path="*" element={<HomePage />} />
            </Routes>
          </SiteWrapper>
        </AuthProvider>
      </BrowserRouter>
    </HelmetProvider>
  </StrictMode>,
);

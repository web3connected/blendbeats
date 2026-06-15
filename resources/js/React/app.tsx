import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { HelmetProvider } from '@dr.pogodin/react-helmet';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './Frontend/components/auth/AuthProvider';
import { PlayerProvider } from './Frontend/components/player/PlayerProvider';
import SiteWrapper from './Frontend/layouts/SiteWrapper';
import AccountPage from './Frontend/pages/auth/AccountPage';
import BillingPaymentsPage from './Frontend/pages/auth/BillingPaymentsPage';
import FeaturedAdAnalyticsPage from './Frontend/pages/auth/FeaturedAdAnalyticsPage';
import FeaturedAdPlacementsPage from './Frontend/pages/auth/FeaturedAdPlacementsPage';
import FeaturedAdsPage from './Frontend/pages/auth/FeaturedAdsPage';
import ForgotPasswordPage from './Frontend/pages/auth/ForgotPasswordPage';
import LoginPage from './Frontend/pages/auth/LoginPage';
import NotificationsPage from './Frontend/pages/auth/NotificationsPage';
import PaymentMethodsPage from './Frontend/pages/auth/PaymentMethodsPage';
import RegisterPage from './Frontend/pages/auth/RegisterPage';
import SettingsPage from './Frontend/pages/auth/SettingsPage';
import StoragePage from './Frontend/pages/auth/StoragePage';
import SupportDocPage from './Frontend/pages/auth/SupportDocPage';
import SupportPage from './Frontend/pages/auth/SupportPage';
import UserDashboardPage from './Frontend/pages/auth/UserDashboardPage';
import BattlesPage from './Frontend/pages/battles';
import DjPortfolioPage from './Frontend/pages/dj/DjPortfolioPage';
import PublicDjProfilePage from './Frontend/pages/dj/PublicDjProfilePage';
import StartDjCareerPage from './Frontend/pages/dj/StartDjCareerPage';
import DjsPage from './Frontend/pages/djs';
import HomePage from './Frontend/pages';
import MerchPage from './Frontend/pages/merch';
import MixesPage from './Frontend/pages/mixes';
import PricingPage from './Frontend/pages/pricing';
import DjLoungePage from './Frontend/pages/social/DjLoungePage';
import SubscriptionCancelPage from './Frontend/pages/subscription-cancel';
import SubscriptionSuccessPage from './Frontend/pages/subscription-success';
import SubscriptionPage from './Frontend/pages/subscription';


const rootElement = document.getElementById('app');

if (! rootElement) {
  throw new Error('Root element not found');
}

createRoot(rootElement).render(
  <StrictMode>
    <HelmetProvider>
      <BrowserRouter>
        <AuthProvider>
          <PlayerProvider>
            <SiteWrapper>
              <Routes>
                <Route path="/" element={<HomePage />} />
                <Route path="/battles" element={<BattlesPage />} />
                <Route path="/mixes" element={<MixesPage />} />
                <Route path="/pricing" element={<PricingPage />} />
                <Route path="/subscription" element={<SubscriptionPage />} />
                <Route path="/subscription/success" element={<SubscriptionSuccessPage />} />
                <Route path="/subscription/cancel" element={<SubscriptionCancelPage />} />
                <Route path="/merch" element={<MerchPage />} />
                <Route path="/djs" element={<DjsPage />} />
                <Route path="/djs/:handle" element={<PublicDjProfilePage />} />
                <Route path="/dj/start" element={<StartDjCareerPage />} />
                <Route path="/dj/edit" element={<StartDjCareerPage />} />
                <Route path="/dj/portfolio" element={<DjPortfolioPage />} />
                <Route path="/dj-lounge" element={<DjLoungePage />} />
                <Route path="/login" element={<LoginPage />} />
                <Route path="/register" element={<RegisterPage />} />
                <Route path="/forgot-password" element={<ForgotPasswordPage />} />
                <Route path="/dashboard" element={<UserDashboardPage />} />
                <Route path="/account" element={<UserDashboardPage />} />
                <Route path="/account/profile" element={<AccountPage />} />
                <Route path="/account/billing" element={<BillingPaymentsPage />} />
                <Route path="/account/featured-ads" element={<FeaturedAdsPage />} />
                <Route path="/account/featured-ads/analytics" element={<FeaturedAdAnalyticsPage />} />
                <Route path="/account/featured-ads/placements" element={<FeaturedAdPlacementsPage />} />
                <Route path="/account/payment-methods" element={<PaymentMethodsPage />} />
                <Route path="/account/notifications" element={<NotificationsPage />} />
                <Route path="/account/settings" element={<SettingsPage />} />
                <Route path="/account/storage" element={<StoragePage />} />
                <Route path="/account/support" element={<SupportPage />} />
                <Route path="/account/support/docs/:topic" element={<SupportDocPage />} />
                <Route path="/settings" element={<Navigate to="/account/settings" replace />} />
                <Route path="*" element={<HomePage />} />
              </Routes>
            </SiteWrapper>
          </PlayerProvider>
        </AuthProvider>
      </BrowserRouter>
    </HelmetProvider>
  </StrictMode>,
);

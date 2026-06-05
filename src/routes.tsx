import { RouteObject } from 'react-router-dom';
import HomePage from './pages/index';
import BattlesPage from './pages/battles';
import DjsPage from './pages/djs';
import GearPage from './pages/gear';
import MerchPage from './pages/merch';
import MixesPage from './pages/mixes';
import ProdNotFoundPage from './pages/_404';
import AccountPage from './pages/auth/AccountPage';
import ForgotPasswordPage from './pages/auth/ForgotPasswordPage';
import LoginPage from './pages/auth/LoginPage';
import RegisterPage from './pages/auth/RegisterPage';

export const routes: RouteObject[] = [
  {
    path: '/',
    element: <HomePage />,
  },
  {
    path: '/battles',
    element: <BattlesPage />,
  },
  {
    path: '/mixes',
    element: <MixesPage />,
  },
  {
    path: '/merch',
    element: <MerchPage />,
  },
  {
    path: '/gear',
    element: <GearPage />,
  },
  {
    path: '/djs',
    element: <DjsPage />,
  },
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/register',
    element: <RegisterPage />,
  },
  {
    path: '/forgot-password',
    element: <ForgotPasswordPage />,
  },
  {
    path: '/account',
    element: <AccountPage />,
  },
  {
    path: '*',
    element: <ProdNotFoundPage />,
  },
];

// Types for type-safe navigation
export type Path =
  | '/'
  | '/battles'
  | '/mixes'
  | '/merch'
  | '/gear'
  | '/djs'
  | '/login'
  | '/register'
  | '/forgot-password'
  | '/account';

export type Params = Record<string, string | undefined>;

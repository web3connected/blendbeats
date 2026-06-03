import { RouteObject } from 'react-router-dom';
import HomePage from './pages/index';
import BattlesPage from './pages/battles';
import DjsPage from './pages/djs';
import GearPage from './pages/gear';
import MerchPage from './pages/merch';
import MixesPage from './pages/mixes';
import ProdNotFoundPage from './pages/_404';

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
    path: '*',
    element: <ProdNotFoundPage />,
  },
];

// Types for type-safe navigation
export type Path = '/' | '/battles' | '/mixes' | '/merch' | '/gear' | '/djs';

export type Params = Record<string, string | undefined>;

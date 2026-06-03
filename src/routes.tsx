import { RouteObject } from 'react-router-dom';
import HomePage from './pages/index';
import ProdNotFoundPage from './pages/_404';

export const routes: RouteObject[] = [
  {
    path: '/',
    element: <HomePage />,
  },
  {
    path: '*',
    element: <ProdNotFoundPage />,
  },
];

// Types for type-safe navigation
export type Path = '/';

export type Params = Record<string, string | undefined>;

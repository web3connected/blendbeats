import { Helmet } from '@dr.pogodin/react-helmet';
import type { ReactNode } from 'react';

import Footer from '@/layouts/parts/Footer';
import Header from '@/layouts/parts/Header';
import Website from '@/layouts/Website';

interface SiteWrapperProps {
  children: ReactNode;
}

export default function SiteWrapper({ children }: SiteWrapperProps) {
  return (
    <Website>
      <Helmet>
        <title>The Blend Battlegrounds</title>
        <meta name="description" content="The premier underground DJ battle platform." />
      </Helmet>
      <Header />
      {children}
      <Footer />
    </Website>
  );
}

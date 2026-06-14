import { Helmet } from '@dr.pogodin/react-helmet';
import type { ReactNode } from 'react';

import MaintenanceGate from '@/components/site/MaintenanceGate';
import Footer from '@/layouts/parts/Footer';
import Header from '@/layouts/parts/Header';
import Website from '@/layouts/Website';

interface SiteWrapperProps {
  children: ReactNode;
}

export default function SiteWrapper({ children }: SiteWrapperProps) {
  return (
    <MaintenanceGate>
      <Website>
        <Helmet>
          <title>The Blend Battlegrounds</title>
          <meta name="description" content="The premier underground DJ battle platform." />
        </Helmet>
        <Header />
        <div className="pt-20">
          <div className="border-b border-[#2a2a2a] bg-[#120b05] px-4 py-3 text-white">
            <div className="container mx-auto flex flex-col gap-1 text-sm leading-6 md:flex-row md:items-center md:justify-center md:text-center">
              <span className="font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                Beta Mode
              </span>
              <span className="text-[#d8d8d8]">
                Thanks for your patience during our development period as we build and improve The Blend Battlegrounds.
              </span>
            </div>
          </div>
          {children}
        </div>
        <Footer />
      </Website>
    </MaintenanceGate>
  );
}

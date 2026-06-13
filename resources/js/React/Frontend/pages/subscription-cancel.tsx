import { Link, useSearchParams } from 'react-router-dom';
import { ArrowLeft, XCircle } from 'lucide-react';

import HeaderTitle from '@/layouts/HeaderTitle';

export default function SubscriptionCancelPage() {
  const [searchParams] = useSearchParams();
  const plan = searchParams.get('plan');

  return (
    <>
      <HeaderTitle
        title="Subscription Cancelled | BlendBeats"
        description="Your BlendBeats subscription checkout was cancelled."
      />

      <main className="grid min-h-[calc(100vh-5rem)] place-items-center bg-[#080808] px-4 py-16 text-white">
        <section className="w-full max-w-2xl border border-[#2a2a2a] bg-[#111] p-8 text-center">
          <XCircle size={34} className="mx-auto text-primary" />
          <p className="mt-5 text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
            Checkout Cancelled
          </p>
          <h1 className="mt-3 text-4xl uppercase md:text-6xl" style={{ fontFamily: 'var(--font-heading)' }}>
            No Charge Made
          </h1>
          <p className="mx-auto mt-5 max-w-lg text-sm leading-6 text-[#aaa]">
            Your membership was not changed. You can return to the subscription page and try again whenever you are ready.
          </p>
          <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <Link
              to={plan ? `/subscription?plan=${plan}` : '/subscription'}
              className="inline-flex h-11 items-center justify-center bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Return To Subscription
            </Link>
            <Link
              to="/pricing"
              className="inline-flex h-11 items-center justify-center gap-2 border border-[#333] px-5 text-xs font-bold uppercase tracking-widest text-[#ddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={14} />
              Pricing
            </Link>
          </div>
        </section>
      </main>
    </>
  );
}

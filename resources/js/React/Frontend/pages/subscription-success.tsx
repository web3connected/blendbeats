import { Link } from 'react-router-dom';
import { CheckCircle2 } from 'lucide-react';

import HeaderTitle from '@/layouts/HeaderTitle';

export default function SubscriptionSuccessPage() {
  return (
    <>
      <HeaderTitle
        title="Subscription Success | BlendBeats"
        description="Your BlendBeats subscription checkout was completed."
      />

      <main className="grid min-h-[calc(100vh-5rem)] place-items-center bg-[#080808] px-4 py-16 text-white">
        <section className="w-full max-w-2xl border border-[#2a2a2a] bg-[#111] p-8 text-center">
          <CheckCircle2 size={34} className="mx-auto text-primary" />
          <p className="mt-5 text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
            Stripe Checkout Complete
          </p>
          <h1 className="mt-3 text-4xl uppercase md:text-6xl" style={{ fontFamily: 'var(--font-heading)' }}>
            Membership Processing
          </h1>
          <p className="mx-auto mt-5 max-w-lg text-sm leading-6 text-[#aaa]">
            Stripe has accepted the checkout. Your membership tier will update as soon as the webhook sync finishes.
          </p>
          <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <Link
              to="/account"
              className="inline-flex h-11 items-center justify-center bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Go To Account
            </Link>
            <Link
              to="/subscription"
              className="inline-flex h-11 items-center justify-center border border-[#333] px-5 text-xs font-bold uppercase tracking-widest text-[#ddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              View Subscription
            </Link>
          </div>
        </section>
      </main>
    </>
  );
}

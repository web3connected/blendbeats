import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  CheckCircle2,
  CreditCard,
  LayoutGrid,
  Loader2,
  Megaphone,
  Radio,
  ShieldCheck,
  UserRound,
  XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  BillingApiError,
  getPaymentMethods,
  type PaymentProfile,
} from '@/lib/billing';

type Requirement = {
  key: string;
  title: string;
  description: string;
  complete: boolean;
  action: string;
  href: string;
};

const steps = ['Getting Started', 'Requirements', 'Agreement', 'Dashboard'];

const dashboardCards = [
  ['Available Placements', 'Browse claimable featured DJ and mix slots.', LayoutGrid],
  ['Campaign History', 'Review active, pending, and completed promotions.', Radio],
  ['Promotion Readiness', 'Track profile, billing, and creative requirements.', ShieldCheck],
];

export default function FeaturedAdsPage() {
  const { user, isLoading } = useAuth();
  const [paymentProfile, setPaymentProfile] = useState<PaymentProfile | null>(null);
  const [isPaymentLoading, setIsPaymentLoading] = useState(true);
  const [paymentError, setPaymentError] = useState('');
  const [currentStep, setCurrentStep] = useState(0);
  const [agreementAccepted, setAgreementAccepted] = useState(false);
  const [onboardingComplete, setOnboardingComplete] = useState(false);

  useEffect(() => {
    if (!user) return;

    let cancelled = false;

    setIsPaymentLoading(true);
    setPaymentError('');
    getPaymentMethods()
      .then((response) => {
        if (!cancelled) setPaymentProfile(response.payment_profile);
      })
      .catch((error) => {
        if (!cancelled) {
          setPaymentError(error instanceof BillingApiError ? error.message : 'Unable to load payment provider status.');
        }
      })
      .finally(() => {
        if (!cancelled) setIsPaymentLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [user]);

  useEffect(() => {
    if (!user) return;

    const storageKey = `blendbeats.featuredAds.onboarding.${user.id}`;
    const saved = window.localStorage.getItem(storageKey) === 'complete';

    setOnboardingComplete(saved);
    setCurrentStep(saved ? 3 : 0);
  }, [user]);

  const requirements = useMemo<Requirement[]>(() => {
    const profile = user?.profile;
    const djProfile = user?.dj_profile;
    const hasDjProfile = Boolean(djProfile?.id);
    const publicDjProfile = Boolean(djProfile && djProfile.profile_status === 'active' && djProfile.visibility === 'public');
    const hasContact = Boolean(profile?.contact_email || user?.email);
    const hasLocation = Boolean(profile?.city || profile?.state || profile?.country);
    const hasPaymentProvider = Boolean(paymentProfile?.primary_provider?.credentials_ready);

    return [
      {
        key: 'dj-profile',
        title: 'Public DJ Profile',
        description: publicDjProfile
          ? 'Your DJ profile is active and public.'
          : hasDjProfile
            ? 'Set your DJ profile to active and public before advertising.'
            : 'Create a DJ profile before claiming featured placements.',
        complete: publicDjProfile,
        action: hasDjProfile ? 'Edit DJ Profile' : 'Create DJ Profile',
        href: hasDjProfile ? '/dj/edit' : '/dj/start',
      },
      {
        key: 'contact',
        title: 'Account Contact',
        description: hasContact
          ? 'A contact email is available for campaign receipts and placement notices.'
          : 'Add a contact email so campaign messages can reach you.',
        complete: hasContact,
        action: 'Update Profile',
        href: '/account/profile',
      },
      {
        key: 'location',
        title: 'Profile Location',
        description: hasLocation
          ? 'Your location data can help match placements and local visibility later.'
          : 'Add at least city, state, or country to strengthen campaign context.',
        complete: hasLocation,
        action: 'Add Location',
        href: '/account/profile',
      },
      {
        key: 'payment',
        title: 'Payment Method Readiness',
        description: hasPaymentProvider
          ? `${paymentProfile?.primary_provider?.display_name} is configured for payments.`
          : 'A configured payment provider is required before campaign checkout can start.',
        complete: hasPaymentProvider,
        action: 'Manage Payment Methods',
        href: '/account/payment-methods',
      },
    ];
  }, [paymentProfile, user]);

  if (isLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20">
        <div className="container mx-auto max-w-6xl">
          <div className="h-48 animate-pulse bg-[#141414]" />
        </div>
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  const completedCount = requirements.filter((requirement) => requirement.complete).length;
  const isReady = completedCount === requirements.length && !isPaymentLoading;
  const storageKey = `blendbeats.featuredAds.onboarding.${user.id}`;

  const goNext = () => {
    if (currentStep === 1 && !isReady) return;
    if (currentStep === 2 && !agreementAccepted) return;

    if (currentStep === 2 && agreementAccepted) {
      window.localStorage.setItem(storageKey, 'complete');
      setOnboardingComplete(true);
      setCurrentStep(3);
      return;
    }

    setCurrentStep((step) => Math.min(step + 1, 3));
  };

  const resetOnboarding = () => {
    window.localStorage.removeItem(storageKey);
    setAgreementAccepted(false);
    setOnboardingComplete(false);
    setCurrentStep(0);
  };

  return (
    <>
      <Helmet>
        <title>Featured Ads | The Blend Battlegrounds</title>
        <meta
          name="description"
          content="Prepare and manage BlendBeats featured advertising campaigns."
        />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <Link
              to="/account/settings"
              className="mb-10 inline-flex h-11 items-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              Settings
            </Link>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
              <div>
                <p className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                  Account / Advertising
                </p>
                <h1
                  className="uppercase leading-none text-white"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 8vw, 6.5rem)' }}
                >
                  Featured Ads
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  Prepare your account for featured placements, then manage campaigns, visibility, and future promotional checkout.
                </p>
              </div>

              <div className="border border-[#303030] bg-[#111111] p-5">
                <div className="mb-4 flex h-12 w-12 items-center justify-center bg-primary text-white">
                  <Megaphone size={20} />
                </div>
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Readiness</p>
                <p className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {completedCount}/{requirements.length}
                </p>
                <p className="mt-2 text-sm leading-6 text-[#888888]">
                  {onboardingComplete ? 'Onboarding complete. Advertising dashboard is available.' : 'Complete onboarding before launching campaigns.'}
                </p>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
            <aside className="border border-[#2a2a2a] bg-[#111111] p-5">
              <div className="mb-5 flex h-12 w-12 items-center justify-center bg-[#080808] text-primary">
                <UserRound size={20} />
              </div>
              <p className="text-[11px] font-bold uppercase tracking-widest text-[#777777]">Advertiser</p>
              <p className="mt-3 truncate text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {user.dj_profile?.dj_name || user.name}
              </p>
              <p className="mt-1 break-all text-sm text-[#888888]">{user.email}</p>
              <div className="mt-6 border-t border-[#262626] pt-5">
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Payment Provider</p>
                <p className="mt-2 text-sm leading-6 text-[#aaaaaa]">
                  {isPaymentLoading
                    ? 'Checking payment provider status...'
                    : paymentProfile?.primary_provider
                      ? `${paymentProfile.primary_provider.display_name} (${paymentProfile.primary_provider.mode})`
                      : 'No active provider configured.'}
                </p>
              </div>
            </aside>

            <div className="space-y-6">
              {!onboardingComplete ? (
                <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                  <div className="mb-6 flex items-center justify-between gap-4">
                    <div>
                      <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                        Step {currentStep + 1} / 4
                      </p>
                      <h2 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        {steps[currentStep]}
                      </h2>
                    </div>
                    {isPaymentLoading && <Loader2 className="animate-spin text-primary" size={22} />}
                  </div>

                  <div className="mb-6 grid gap-2 sm:grid-cols-4">
                    {steps.map((step, index) => (
                      <button
                        key={step}
                        type="button"
                        onClick={() => setCurrentStep(index)}
                        className={`border px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest ${
                          index === currentStep
                            ? 'border-primary bg-primary text-white'
                            : index < currentStep
                              ? 'border-[#FFB800] bg-[#151106] text-[#FFB800]'
                              : 'border-[#2a2a2a] bg-[#080808] text-[#777777]'
                        }`}
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        {index + 1}. {step}
                      </button>
                    ))}
                  </div>

                  {paymentError && <div className="mb-4 border border-primary bg-[#160808] p-4 text-sm text-[#dddddd]">{paymentError}</div>}

                  {currentStep === 0 && (
                    <div className="border border-[#2a2a2a] bg-[#080808] p-6">
                      <Megaphone className="text-primary" size={28} />
                      <h3 className="mt-5 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        Featured Placement Setup
                      </h3>
                      <p className="mt-4 max-w-3xl text-sm leading-6 text-[#aaaaaa]">
                        Featured Ads help DJs get extra visibility in premium BlendBeats areas. Before you can claim paid placements, we need to confirm your public DJ profile, contact details, payment readiness, and agreement to the advertising terms.
                      </p>
                      <div className="mt-6 grid gap-3 md:grid-cols-3">
                        {['Validate account readiness', 'Review ad responsibilities', 'Unlock campaign dashboard'].map((item) => (
                          <div key={item} className="border border-[#2a2a2a] bg-[#111111] p-4">
                            <CheckCircle2 className="text-[#FFB800]" size={18} />
                            <p className="mt-3 text-sm font-bold uppercase tracking-widest text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                              {item}
                            </p>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {currentStep === 1 && (
                    <div className="grid gap-3">
                      {requirements.map((requirement) => (
                        <article key={requirement.key} className="grid gap-4 border border-[#2a2a2a] bg-[#080808] p-4 md:grid-cols-[1fr_auto] md:items-center">
                          <div className="flex gap-4">
                            {requirement.complete ? (
                              <CheckCircle2 className="mt-1 shrink-0 text-[#FFB800]" size={22} />
                            ) : (
                              <XCircle className="mt-1 shrink-0 text-primary" size={22} />
                            )}
                            <div>
                              <h3 className="text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                                {requirement.title}
                              </h3>
                              <p className="mt-2 text-sm leading-6 text-[#888888]">{requirement.description}</p>
                            </div>
                          </div>
                          <Link
                            to={requirement.href}
                            className={`inline-flex h-11 items-center justify-center gap-2 px-4 text-xs font-bold uppercase tracking-widest ${
                              requirement.complete
                                ? 'border border-[#333333] text-[#777777]'
                                : 'bg-primary text-white'
                            }`}
                            style={{ fontFamily: 'var(--font-heading)' }}
                          >
                            {requirement.complete ? 'Review' : requirement.action}
                            <ArrowRight size={15} />
                          </Link>
                        </article>
                      ))}
                    </div>
                  )}

                  {currentStep === 2 && (
                    <div className="border border-[#2a2a2a] bg-[#080808] p-6">
                      <ShieldCheck className="text-[#FFB800]" size={28} />
                      <h3 className="mt-5 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        Advertising Agreement
                      </h3>
                      <div className="mt-4 space-y-3 text-sm leading-6 text-[#aaaaaa]">
                        <p>You agree that submitted campaigns must represent your own DJ profile, mix, event, or approved brand placement.</p>
                        <p>You agree not to promote misleading, abusive, illegal, private, unpublished, or rights-infringing content.</p>
                        <p>You understand featured placement increases visibility but does not guarantee plays, followers, ratings, bookings, or revenue.</p>
                        <p>You understand campaigns may be reviewed, paused, rejected, or removed if they violate BlendBeats rules.</p>
                      </div>
                      <label className="mt-6 flex cursor-pointer gap-3 border border-[#333333] bg-[#111111] p-4 text-sm text-white">
                        <input
                          type="checkbox"
                          checked={agreementAccepted}
                          onChange={(event) => setAgreementAccepted(event.target.checked)}
                          className="mt-1"
                        />
                        <span>I agree to the BlendBeats featured advertising terms and understand campaign approval requirements.</span>
                      </label>
                    </div>
                  )}

                  <div className="mt-6 flex items-center justify-between border-t border-[#262626] pt-5">
                    <button
                      type="button"
                      onClick={() => setCurrentStep((step) => Math.max(step - 1, 0))}
                      disabled={currentStep === 0}
                      className="inline-flex h-11 items-center justify-center border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#aaaaaa] disabled:cursor-not-allowed disabled:opacity-40"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      Back
                    </button>
                    <div className="text-right">
                      {currentStep === 1 && !isReady && (
                        <p className="mb-2 text-xs text-[#888888]">Complete all requirements before continuing.</p>
                      )}
                      {currentStep === 2 && !agreementAccepted && (
                        <p className="mb-2 text-xs text-[#888888]">Accept the agreement to unlock the dashboard.</p>
                      )}
                      <button
                        type="button"
                        onClick={goNext}
                        disabled={(currentStep === 1 && !isReady) || (currentStep === 2 && !agreementAccepted)}
                        className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white disabled:cursor-not-allowed disabled:opacity-50"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        {currentStep === 2 ? 'Finish Setup' : 'Continue'}
                        <ArrowRight size={15} />
                      </button>
                    </div>
                  </div>
                </section>
              ) : (
                <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                  <div className="mb-6 flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                      <BadgeCheck className="text-[#FFB800]" size={24} />
                      <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        Advertising Dashboard
                      </h2>
                    </div>
                    <button
                      type="button"
                      onClick={resetOnboarding}
                      className="border border-[#333333] px-4 py-3 text-xs font-bold uppercase tracking-widest text-[#aaaaaa]"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      Review Setup
                    </button>
                  </div>
                  <div className="grid gap-4 md:grid-cols-3">
                    {dashboardCards.map(([title, body, Icon]) => (
                      <article key={title as string} className="border border-[#2a2a2a] bg-[#080808] p-5">
                        <Icon className="text-primary" size={22} />
                        <h3 className="mt-5 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {title as string}
                        </h3>
                        <p className="mt-3 text-sm leading-6 text-[#888888]">{body as string}</p>
                      </article>
                    ))}
                  </div>
                  <div className="mt-5 border border-[#333333] bg-[#080808] p-5">
                    <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                      Next Build Step
                    </p>
                    <p className="mt-3 text-sm leading-6 text-[#aaaaaa]">
                      Claimable featured slots, campaign checkout, and active campaign reporting will connect here next.
                    </p>
                  </div>
                </section>
              )}

              <section className="grid gap-4 md:grid-cols-2">
                <article className="border border-[#2a2a2a] bg-[#111111] p-5">
                  <CreditCard className="text-[#FFB800]" size={22} />
                  <h3 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Payment First
                  </h3>
                  <p className="mt-3 text-sm leading-6 text-[#888888]">
                    Campaign purchases need an active configured payment provider before users can claim paid placement.
                  </p>
                </article>
                <article className="border border-[#2a2a2a] bg-[#111111] p-5">
                  <Megaphone className="text-primary" size={22} />
                  <h3 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Visibility Controls
                  </h3>
                  <p className="mt-3 text-sm leading-6 text-[#888888]">
                    The dashboard is structured for placement slots, campaign duration options, featured profiles, and promotion reporting.
                  </p>
                </article>
              </section>
            </div>
          </div>
        </section>
      </main>
    </>
  );
}

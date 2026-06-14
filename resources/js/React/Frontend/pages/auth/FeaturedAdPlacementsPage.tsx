import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  CreditCard,
  LayoutGrid,
  Loader2,
  Radio,
  ShieldCheck,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, Navigate, useSearchParams } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  captureFeaturedAdCampaign,
  FeaturedAdsApiError,
  getFeaturedAdPlacements,
  startFeaturedAdCheckout,
  type FeaturedAdsPlacementsResponse,
  type FeaturedCampaignSlot,
} from '@/lib/featured-ads';

export default function FeaturedAdPlacementsPage() {
  const { user, isLoading } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [placements, setPlacements] = useState<FeaturedAdsPlacementsResponse | null>(null);
  const [isPlacementsLoading, setIsPlacementsLoading] = useState(false);
  const [placementsError, setPlacementsError] = useState('');
  const [selectedOptionBySlot, setSelectedOptionBySlot] = useState<Record<number, number>>({});
  const [checkoutSlot, setCheckoutSlot] = useState<number | null>(null);
  const [captureMessage, setCaptureMessage] = useState('');

  const loadPlacements = () => {
    if (!user) return;

    setIsPlacementsLoading(true);
    setPlacementsError('');
    getFeaturedAdPlacements()
      .then((response) => {
        setPlacements(response);
        setSelectedOptionBySlot((current) => {
          const next = { ...current };
          response.campaigns.forEach((campaign) => {
            campaign.slots.forEach((slot) => {
              if (!next[slot.id] && slot.options[0]) {
                next[slot.id] = slot.options[0].id;
              }
            });
          });

          return next;
        });
      })
      .catch((error) => {
        setPlacementsError(error instanceof FeaturedAdsApiError ? error.message : 'Unable to load featured placements.');
      })
      .finally(() => setIsPlacementsLoading(false));
  };

  useEffect(() => {
    loadPlacements();
  }, [user]);

  useEffect(() => {
    if (!user) return;
    if (searchParams.get('payment') !== 'paypal-return') return;

    const campaignId = Number(searchParams.get('campaign'));
    if (!campaignId) return;

    setCaptureMessage('Finalizing PayPal payment...');
    captureFeaturedAdCampaign(campaignId)
      .then(() => {
        setCaptureMessage('Payment complete. Your featured placement is now active.');
        loadPlacements();
      })
      .catch((error) => {
        setCaptureMessage(error instanceof FeaturedAdsApiError ? error.message : 'PayPal payment could not be finalized.');
      })
      .finally(() => {
        setSearchParams((currentParams) => {
          const nextParams = new URLSearchParams(currentParams);
          nextParams.delete('campaign');
          nextParams.delete('payment');
          nextParams.delete('token');
          nextParams.delete('PayerID');
          return nextParams;
        }, { replace: true });
      });
  }, [user, searchParams, setSearchParams]);

  const handleCheckout = (slot: FeaturedCampaignSlot) => {
    const selectedOptionId = selectedOptionBySlot[slot.id] || slot.options[0]?.id;

    if (!selectedOptionId) {
      setPlacementsError('Choose a campaign option before checkout.');
      return;
    }

    setCheckoutSlot(slot.id);
    setPlacementsError('');

    startFeaturedAdCheckout(slot.id, selectedOptionId)
      .then((response) => {
        if (response.checkout_url) {
          window.location.href = response.checkout_url;
          return;
        }

        setPlacementsError('Checkout started, but the payment provider did not return a checkout URL.');
        loadPlacements();
      })
      .catch((error) => {
        setPlacementsError(error instanceof FeaturedAdsApiError ? error.message : 'Unable to start featured placement checkout.');
      })
      .finally(() => setCheckoutSlot(null));
  };

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

  const openSlotCount = placements
    ? placements.campaigns.reduce(
      (total, campaign) => total + campaign.slots.filter((slot) => slot.is_available && slot.is_unlocked).length,
      0,
    )
    : 0;

  return (
    <>
      <Helmet>
        <title>Available Placements | The Blend Battlegrounds</title>
        <meta name="description" content="Browse and claim featured ad campaign placements." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <Link
              to="/account/featured-ads"
              className="mb-10 inline-flex h-11 items-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              Featured Ads
            </Link>

            <p className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
              Advertising Marketplace
            </p>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-end">
              <div>
                <h1
                  className="uppercase leading-none text-white"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 8vw, 6.5rem)' }}
                >
                  Available Placements
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  Browse campaign offers. Each campaign reuses a group template with its own claimable slots.
                </p>
              </div>
              <div className="border border-[#303030] bg-[#111111] p-5">
                <LayoutGrid className="text-primary" size={24} />
                <p className="mt-5 text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Open Slots</p>
                <p className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {isPlacementsLoading ? '...' : openSlotCount}
                </p>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            {captureMessage && (
              <div className="mb-5 border border-[#FFB800]/40 bg-[#151106] p-4 text-sm leading-6 text-[#dddddd]">
                {captureMessage}
              </div>
            )}

            {placementsError && (
              <div className="mb-5 border border-primary bg-[#160808] p-4 text-sm leading-6 text-[#dddddd]">
                {placementsError}
              </div>
            )}

            {isPlacementsLoading && (
              <div className="flex min-h-40 items-center justify-center border border-[#2a2a2a] bg-[#080808] text-[#888888]">
                <Loader2 className="mr-3 animate-spin text-primary" size={20} />
                Loading available placements
              </div>
            )}

            {!isPlacementsLoading && placements && (
              <div className="grid gap-6">
                <div className="grid gap-4 md:grid-cols-3">
                  <article className="border border-[#2a2a2a] bg-[#080808] p-5">
                    <LayoutGrid className="text-primary" size={22} />
                    <h3 className="mt-5 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Available Placements
                    </h3>
                    <p className="mt-3 text-sm leading-6 text-[#888888]">{openSlotCount} slots are open for your current tier.</p>
                  </article>
                  <article className="border border-[#2a2a2a] bg-[#080808] p-5">
                    <Radio className="text-[#FFB800]" size={22} />
                    <h3 className="mt-5 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Campaign History
                    </h3>
                    <p className="mt-3 text-sm leading-6 text-[#888888]">
                      {placements.my_campaigns.length} campaign{placements.my_campaigns.length === 1 ? '' : 's'} attached to your DJ profile.
                    </p>
                  </article>
                  <article className="border border-[#2a2a2a] bg-[#080808] p-5">
                    <ShieldCheck className="text-primary" size={22} />
                    <h3 className="mt-5 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Promotion Access
                    </h3>
                    <p className="mt-3 text-sm leading-6 text-[#888888]">
                      Your tier can claim Groups {placements.membership.groups.join(', ') || 'none'}.
                    </p>
                  </article>
                </div>

                <section className="border border-[#2a2a2a] bg-[#080808] p-5">
                  <div className="mb-5 flex flex-col gap-3 border-b border-[#262626] pb-5 md:flex-row md:items-end md:justify-between">
                    <div>
                      <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                        Browse Claimable Featured DJ & Mix Slots
                      </p>
                      <h2 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        Campaign Offers
                      </h2>
                    </div>
                  </div>

                  <div className="grid gap-5">
                    {placements.campaigns.map((campaign) => (
                      <article key={campaign.id} className="border border-[#2a2a2a] bg-[#101010] p-4">
                        <div className="mb-4 flex flex-col gap-3 border-b border-[#262626] pb-4 md:flex-row md:items-start md:justify-between">
                          <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
                              Campaign / Group {campaign.group} Template
                            </p>
                            <h3 className="mt-2 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                              {campaign.title}
                            </h3>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-[#888888]">
                              {campaign.description || `${campaign.group_name} with ${campaign.slot_count} claimable positions.`}
                            </p>
                          </div>
                          <span className="border border-[#333333] px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
                            {campaign.daily_price_label}/day
                          </span>
                        </div>

                        {!campaign.is_unlocked && (
                          <div className="mb-4 border border-[#2a2a2a] bg-[#080808] p-3 text-sm leading-6 text-[#888888]">
                            Upgrade your membership to access Group {campaign.group} campaigns.
                          </div>
                        )}

                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                          {campaign.slots.map((slot) => {
                            const selectedOptionId = selectedOptionBySlot[slot.id] || slot.options[0]?.id || 0;
                            const selectedOption = slot.options.find((option) => option.id === selectedOptionId) || slot.options[0];
                            const canCheckout = Boolean(slot.is_unlocked && slot.is_available && selectedOption && placements.payment_provider?.credentials_ready);

                            return (
                              <div
                                key={slot.id}
                                className={`border p-4 ${
                                  canCheckout ? 'border-[#333333] bg-[#080808]' : 'border-[#222222] bg-[#0b0b0b] opacity-75'
                                }`}
                              >
                                <div className="mb-4">
                                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">
                                    Group {slot.group} / Slot {slot.group_slot_number}
                                  </p>
                                  <h4 className="mt-2 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                                    Position {slot.group_slot_number}
                                  </h4>
                                </div>

                                {slot.active_campaign ? (
                                  <div className="border border-[#2a2a2a] bg-[#111111] p-3 text-sm leading-6 text-[#aaaaaa]">
                                    Claimed by {slot.active_campaign.dj?.name || 'a DJ'}.
                                    <span className="mt-1 block text-[11px] uppercase tracking-widest text-[#777777]">
                                      {slot.active_campaign.status.replaceAll('_', ' ')}
                                    </span>
                                  </div>
                                ) : !slot.is_unlocked ? (
                                  <div className="border border-[#2a2a2a] bg-[#111111] p-3 text-sm leading-6 text-[#888888]">
                                    Locked for your current tier.
                                  </div>
                                ) : slot.options.length === 0 ? (
                                  <div className="border border-[#2a2a2a] bg-[#111111] p-3 text-sm leading-6 text-[#888888]">
                                    This position is not configured for claims yet.
                                  </div>
                                ) : (
                                  <div className="grid gap-3">
                                    <label className="grid gap-2">
                                      <span className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Campaign Length</span>
                                      <select
                                        value={selectedOptionId}
                                        onChange={(event) =>
                                          setSelectedOptionBySlot((current) => ({
                                            ...current,
                                            [slot.id]: Number(event.target.value),
                                          }))
                                        }
                                        className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                                      >
                                        {slot.options.map((option) => (
                                          <option key={option.id} value={option.id}>
                                            {option.name} - {option.price_label}
                                          </option>
                                        ))}
                                      </select>
                                    </label>
                                    <p className="text-xs leading-5 text-[#888888]">
                                      {selectedOption?.description || `${selectedOption?.duration_days ?? 1} day featured placement.`}
                                    </p>
                                    <button
                                      type="button"
                                      onClick={() => handleCheckout(slot)}
                                      disabled={!canCheckout || checkoutSlot === slot.id}
                                      className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-[#d91515] disabled:cursor-not-allowed disabled:opacity-50"
                                      style={{ fontFamily: 'var(--font-heading)' }}
                                    >
                                      {checkoutSlot === slot.id ? <Loader2 className="animate-spin" size={15} /> : <CreditCard size={15} />}
                                      {selectedOption ? `Buy ${selectedOption.price_label}` : 'Buy Slot'}
                                    </button>
                                  </div>
                                )}
                              </div>
                            );
                          })}
                        </div>
                      </article>
                    ))}
                  </div>
                </section>
              </div>
            )}
          </div>
        </section>
      </main>
    </>
  );
}

import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  CreditCard,
  LayoutGrid,
  Loader2,
  Radio,
  ShieldCheck,
  X,
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
  type FeaturedMarketplaceCampaign,
  type FeaturedCampaignSlot,
} from '@/lib/featured-ads';

export default function FeaturedAdPlacementsPage() {
  const { user, isLoading } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [placements, setPlacements] = useState<FeaturedAdsPlacementsResponse | null>(null);
  const [isPlacementsLoading, setIsPlacementsLoading] = useState(false);
  const [placementsError, setPlacementsError] = useState('');
  const [selectedOptionBySlot, setSelectedOptionBySlot] = useState<Record<number, number>>({});
  const [selectedSlotId, setSelectedSlotId] = useState<number | null>(null);
  const [campaignSetupSlotId, setCampaignSetupSlotId] = useState<number | null>(null);
  const [checkoutSlot, setCheckoutSlot] = useState<number | null>(null);
  const [captureMessage, setCaptureMessage] = useState('');

  const loadPlacements = () => {
    if (!user) return;

    setIsPlacementsLoading(true);
    setPlacementsError('');
    getFeaturedAdPlacements()
      .then((response) => {
        setPlacements(response);
        setSelectedSlotId((current) => {
          if (current && response.campaigns.some((campaign) => campaign.slots.some((slot) => slot.id === current))) {
            return current;
          }

          return response.campaigns[0]?.slots[0]?.id ?? null;
        });
        setSelectedOptionBySlot((current) => {
          const next = { ...current };
          response.campaigns.forEach((campaign) => {
            campaign.slots.forEach((slot) => {
              if (!next[slot.id] && slot.options[0]) {
                next[slot.id] = slot.options[0].id;
              }
              if (slot.active_campaign?.is_mine && slot.active_campaign.campaign_option_id) {
                next[slot.id] = slot.active_campaign.campaign_option_id;
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
  const selectedPlacement = placements
    ? placements.campaigns.reduce<{
      campaign: FeaturedMarketplaceCampaign;
      slot: FeaturedCampaignSlot;
    } | null>((selected, campaign) => {
      if (selected) return selected;
      const slot = campaign.slots.find((campaignSlot) => campaignSlot.id === selectedSlotId);
      return slot ? { campaign, slot } : null;
    }, null)
    : null;
  const selectedSlot = selectedPlacement?.slot ?? null;
  const selectedCampaign = selectedPlacement?.campaign ?? null;
  const campaignSetupPlacement = placements
    ? placements.campaigns.reduce<{
      campaign: FeaturedMarketplaceCampaign;
      slot: FeaturedCampaignSlot;
    } | null>((selected, campaign) => {
      if (selected) return selected;
      const slot = campaign.slots.find((campaignSlot) => campaignSlot.id === campaignSetupSlotId);
      return slot ? { campaign, slot } : null;
    }, null)
    : null;
  const campaignSetupSlot = campaignSetupPlacement?.slot ?? null;
  const campaignSetupCampaign = campaignSetupPlacement?.campaign ?? null;
  const campaignSetupOptionId = campaignSetupSlot ? selectedOptionBySlot[campaignSetupSlot.id] || campaignSetupSlot.options[0]?.id || 0 : 0;
  const campaignSetupOption = campaignSetupSlot?.options.find((option) => option.id === campaignSetupOptionId) || campaignSetupSlot?.options[0] || null;
  const campaignSetupActiveCampaign = campaignSetupSlot?.active_campaign ?? null;
  const canResumePayment = Boolean(
    campaignSetupActiveCampaign?.is_mine
      && campaignSetupActiveCampaign.status === 'pending_payment'
      && campaignSetupActiveCampaign.approval_url,
  );
  const canPayCampaignSetup = Boolean(
    canResumePayment
      || (
        campaignSetupSlot?.is_unlocked
        && campaignSetupSlot.is_available
        && campaignSetupOption
        && placements?.payment_provider?.credentials_ready
      ),
  );

  const openCampaignSetup = (slot: FeaturedCampaignSlot) => {
    setSelectedSlotId(slot.id);
    setCampaignSetupSlotId(slot.id);
  };

  const handleCampaignSetupPayment = (slot: FeaturedCampaignSlot) => {
    const activeCampaign = slot.active_campaign;

    if (activeCampaign?.is_mine && activeCampaign.status === 'pending_payment') {
      if (activeCampaign.approval_url) {
        window.location.href = activeCampaign.approval_url;
        return;
      }

      setPlacementsError('This campaign is pending payment, but the payment link could not be found. Please start a new campaign or contact support.');
      return;
    }

    handleCheckout(slot);
  };

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

                  <div className="mb-5 grid gap-4 border-b border-[#262626] pb-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="border border-[#2a2a2a] bg-[#101010] p-4">
                      <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                        Selected Slot Preview
                      </p>
                      {selectedSlot && selectedCampaign ? (
                        <div className="mt-4 grid gap-4">
                          <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
                              {selectedCampaign.title}
                            </p>
                            <h3 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                              Group {selectedSlot.group} / Slot {selectedSlot.group_slot_number}
                            </h3>
                          </div>

                          <div className="grid gap-3 sm:grid-cols-4">
                            <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Daily Rate</p>
                              <p className="mt-2 text-2xl text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                                {selectedSlot.daily_price_label}
                              </p>
                            </div>
                            <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Exposure</p>
                              <p className="mt-2 text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                                {selectedSlot.exposure_percent}%
                              </p>
                            </div>
                            <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Weight</p>
                              <p className="mt-2 text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                                {selectedSlot.rotation_weight}
                              </p>
                            </div>
                            <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Status</p>
                              <p className="mt-2 text-2xl capitalize text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                                {selectedSlot.claim_status.replaceAll('_', ' ')}
                              </p>
                            </div>
                          </div>

                          <p className="text-sm leading-6 text-[#999999]">
                            This preview shows the real marketplace value for this exact placement. The selected campaign length multiplies the daily rate by the number of days.
                          </p>
                        </div>
                      ) : (
                        <p className="mt-4 text-sm text-[#888888]">Select a campaign slot to preview its pricing and placement data.</p>
                      )}
                    </div>

                    <div className="border border-[#2a2a2a] bg-[#101010] p-4">
                      <p className="text-xs font-bold uppercase tracking-widest text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                        Claim Setup
                      </p>
                      {selectedSlot && selectedCampaign ? (
                        <div className="mt-4 grid gap-3">
                          {!selectedSlot.is_unlocked && (
                            <div className="border border-[#2a2a2a] bg-[#080808] p-3 text-sm leading-6 text-[#888888]">
                              Upgrade your membership to access Group {selectedSlot.group} campaigns.
                            </div>
                          )}
                          {selectedSlot.active_campaign && (
                            <div className="border border-[#2a2a2a] bg-[#080808] p-3 text-sm leading-6 text-[#aaaaaa]">
                              Claimed by {selectedSlot.active_campaign.dj?.name || 'a DJ'}.
                            </div>
                          )}
                          {selectedSlot.active_campaign?.is_mine && selectedSlot.active_campaign.status === 'pending_payment' && (
                            <button
                              type="button"
                              onClick={() => openCampaignSetup(selectedSlot)}
                              className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-[#d91515]"
                              style={{ fontFamily: 'var(--font-heading)' }}
                            >
                              <CreditCard size={15} />
                              Continue Campaign
                            </button>
                          )}
                          {selectedSlot.is_unlocked && selectedSlot.is_available && selectedSlot.options.length > 0 && (
                            <>
                              <button
                                type="button"
                                onClick={() => openCampaignSetup(selectedSlot)}
                                className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-[#d91515] disabled:cursor-not-allowed disabled:opacity-50"
                                style={{ fontFamily: 'var(--font-heading)' }}
                              >
                                <CreditCard size={15} />
                                Set Up Campaign
                              </button>
                            </>
                          )}
                          {selectedSlot.is_unlocked && selectedSlot.is_available && selectedSlot.options.length === 0 && (
                            <div className="border border-[#2a2a2a] bg-[#080808] p-3 text-sm leading-6 text-[#888888]">
                              This position is not configured for claims yet.
                            </div>
                          )}
                        </div>
                      ) : (
                        <p className="mt-4 text-sm text-[#888888]">No slot selected.</p>
                      )}
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
                            {campaign.daily_price_range_label}/day
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
                                className={`border p-4 transition-colors ${
                                  selectedSlotId === slot.id
                                    ? 'border-primary bg-[#140909]'
                                    : canCheckout
                                      ? 'border-[#333333] bg-[#080808]'
                                      : 'border-[#222222] bg-[#0b0b0b] opacity-75'
                                }`}
                              >
                                <div className="mb-4">
                                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">
                                    Group {slot.group} / Slot {slot.group_slot_number}
                                  </p>
                                  <h4 className="mt-2 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                                    Position {slot.group_slot_number}
                                  </h4>
                                  <div className="mt-3 grid grid-cols-2 gap-2 text-[10px] font-bold uppercase tracking-widest">
                                    <span className="border border-[#252525] bg-[#101010] px-2 py-2 text-[#FFB800]">
                                      {slot.daily_price_label}/day
                                    </span>
                                    <span className="border border-[#252525] bg-[#101010] px-2 py-2 text-[#aaaaaa]">
                                      {slot.exposure_percent}% exposure
                                    </span>
                                  </div>
                                  <button
                                    type="button"
                                    onClick={() => setSelectedSlotId(slot.id)}
                                    className="mt-3 inline-flex h-9 w-full items-center justify-center border border-[#333333] px-3 text-[10px] font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                                    style={{ fontFamily: 'var(--font-heading)' }}
                                  >
                                    Preview Slot
                                  </button>
                                </div>

                                {slot.active_campaign ? (
                                  <div className="grid gap-3">
                                    <div className="border border-[#2a2a2a] bg-[#111111] p-3 text-sm leading-6 text-[#aaaaaa]">
                                      Claimed by {slot.active_campaign.dj?.name || 'a DJ'}.
                                      <span className="mt-1 block text-[11px] uppercase tracking-widest text-[#777777]">
                                        {slot.active_campaign.status.replaceAll('_', ' ')}
                                      </span>
                                    </div>
                                    {slot.active_campaign.is_mine && slot.active_campaign.status === 'pending_payment' && (
                                      <button
                                        type="button"
                                        onClick={() => openCampaignSetup(slot)}
                                        className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-[#d91515]"
                                        style={{ fontFamily: 'var(--font-heading)' }}
                                      >
                                        <CreditCard size={15} />
                                        Continue Campaign
                                      </button>
                                    )}
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
                                      <div className="border border-[#333333] bg-[#080808] px-3 py-3 text-sm text-[#dddddd]">
                                        Choose days in setup
                                      </div>
                                    </label>
                                    <p className="text-xs leading-5 text-[#888888]">
                                      Preview this slot, then set up campaign duration and payment.
                                    </p>
                                    <button
                                      type="button"
                                      onClick={() => openCampaignSetup(slot)}
                                      disabled={!canCheckout}
                                      className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-[#d91515] disabled:cursor-not-allowed disabled:opacity-50"
                                      style={{ fontFamily: 'var(--font-heading)' }}
                                    >
                                      <CreditCard size={15} />
                                      Set Up Campaign
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

      {campaignSetupSlot && campaignSetupCampaign && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4 py-6">
          <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto border border-[#333333] bg-[#0b0b0b] shadow-2xl">
            <div className="flex items-start justify-between border-b border-[#262626] p-5">
              <div>
                <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                  Campaign Setup
                </p>
                <h2 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Group {campaignSetupSlot.group} / Slot {campaignSetupSlot.group_slot_number}
                </h2>
              </div>
              <button
                type="button"
                onClick={() => setCampaignSetupSlotId(null)}
                className="inline-flex h-10 w-10 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                aria-label="Close campaign setup"
              >
                <X size={18} />
              </button>
            </div>

            <div className="grid gap-5 p-5">
              <div className="border border-[#2a2a2a] bg-[#111111] p-4">
                <p className="text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
                  {campaignSetupCampaign.title}
                </p>
                <p className="mt-3 text-sm leading-6 text-[#aaaaaa]">
                  {campaignSetupCampaign.description || `${campaignSetupCampaign.group_name} placement campaign.`}
                </p>
              </div>

              <div className="grid gap-3 sm:grid-cols-4">
                <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Daily Rate</p>
                  <p className="mt-2 text-2xl text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                    {campaignSetupSlot.daily_price_label}
                  </p>
                </div>
                <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Exposure</p>
                  <p className="mt-2 text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {campaignSetupSlot.exposure_percent}%
                  </p>
                </div>
                <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Weight</p>
                  <p className="mt-2 text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {campaignSetupSlot.rotation_weight}
                  </p>
                </div>
                <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Status</p>
                  <p className="mt-2 text-2xl capitalize text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {campaignSetupSlot.claim_status.replaceAll('_', ' ')}
                  </p>
                </div>
              </div>

              {campaignSetupActiveCampaign?.is_mine && campaignSetupActiveCampaign.status === 'pending_payment' ? (
                <div className="border border-[#FFB800]/40 bg-[#151106] p-4 text-sm leading-6 text-[#dddddd]">
                  This campaign is already started and waiting for payment. You can review the details here, then continue to PayPal.
                </div>
              ) : (
                <label className="grid gap-2">
                  <span className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">How many days?</span>
                  <select
                    value={campaignSetupOptionId}
                    onChange={(event) =>
                      setSelectedOptionBySlot((current) => ({
                        ...current,
                        [campaignSetupSlot.id]: Number(event.target.value),
                      }))
                    }
                    className="h-12 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                  >
                    {campaignSetupSlot.options.map((option) => (
                      <option key={option.id} value={option.id}>
                        {option.name} - {option.price_label}
                      </option>
                    ))}
                  </select>
                </label>
              )}

              <div className="grid gap-3 border border-[#2a2a2a] bg-[#111111] p-4 sm:grid-cols-3">
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Campaign Length</p>
                  <p className="mt-2 text-xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {campaignSetupActiveCampaign?.duration_days ?? campaignSetupOption?.duration_days ?? 0} day{(campaignSetupActiveCampaign?.duration_days ?? campaignSetupOption?.duration_days) === 1 ? '' : 's'}
                  </p>
                </div>
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Daily Rate</p>
                  <p className="mt-2 text-xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {campaignSetupSlot.daily_price_label}
                  </p>
                </div>
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Total Due</p>
                  <p className="mt-2 text-3xl text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                    {campaignSetupActiveCampaign?.amount_label ?? campaignSetupOption?.price_label ?? '$0.00'}
                  </p>
                </div>
              </div>

              {campaignSetupOption?.description && (
                <p className="text-sm leading-6 text-[#999999]">{campaignSetupOption.description}</p>
              )}

              {!placements?.payment_provider?.credentials_ready && (
                <div className="border border-primary bg-[#160808] p-3 text-sm leading-6 text-[#dddddd]">
                  Payment provider is not ready yet. Configure payment methods before checkout.
                </div>
              )}

              <div className="flex flex-col gap-3 border-t border-[#262626] pt-5 sm:flex-row sm:justify-end">
                <button
                  type="button"
                  onClick={() => setCampaignSetupSlotId(null)}
                  className="inline-flex h-11 items-center justify-center border border-[#333333] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Keep Browsing
                </button>
                <button
                  type="button"
                  onClick={() => handleCampaignSetupPayment(campaignSetupSlot)}
                  disabled={!canPayCampaignSetup || checkoutSlot === campaignSetupSlot.id}
                  className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-[#d91515] disabled:cursor-not-allowed disabled:opacity-50"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  {checkoutSlot === campaignSetupSlot.id ? <Loader2 className="animate-spin" size={15} /> : <CreditCard size={15} />}
                  {canResumePayment ? 'Continue To PayPal' : `Pay ${campaignSetupOption?.price_label ?? ''}`}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

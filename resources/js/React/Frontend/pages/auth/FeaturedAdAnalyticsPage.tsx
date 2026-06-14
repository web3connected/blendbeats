import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowLeft, BarChart3, Eye, Loader2, MousePointerClick, Radio } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import MaintenanceGate from '@/components/site/MaintenanceGate';
import {
  FeaturedAdsApiError,
  getFeaturedAdAnalytics,
  type FeaturedAdAnalyticsCampaign,
  type FeaturedAdAnalyticsResponse,
} from '@/lib/featured-ads';

function formatDate(date: string | null) {
  if (!date) return 'Not scheduled';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(date));
}

function campaignLabel(campaign: FeaturedAdAnalyticsCampaign) {
  return campaign.campaign_title || `Group ${campaign.group} / Slot ${campaign.slot_position}`;
}

export default function FeaturedAdAnalyticsPage() {
  const { user, isLoading } = useAuth();
  const [analytics, setAnalytics] = useState<FeaturedAdAnalyticsResponse | null>(null);
  const [isAnalyticsLoading, setIsAnalyticsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!user) return;

    let cancelled = false;

    setIsAnalyticsLoading(true);
    setError('');
    getFeaturedAdAnalytics()
      .then((response) => {
        if (!cancelled) setAnalytics(response);
      })
      .catch((loadError) => {
        if (!cancelled) {
          setError(loadError instanceof FeaturedAdsApiError ? loadError.message : 'Unable to load ad analytics.');
        }
      })
      .finally(() => {
        if (!cancelled) setIsAnalyticsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [user]);

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

  const summary = analytics?.summary;
  const campaigns = analytics?.campaigns ?? [];
  const maxCampaignActivity = Math.max(...campaigns.map((campaign) => Math.max(campaign.impressions, campaign.clicks)), 1);
  const groupTotals = campaigns.reduce<Record<string, { impressions: number; clicks: number }>>((totals, campaign) => {
    const key = `Group ${campaign.group}`;
    const current = totals[key] ?? { impressions: 0, clicks: 0 };

    return {
      ...totals,
      [key]: {
        impressions: current.impressions + campaign.impressions,
        clicks: current.clicks + campaign.clicks,
      },
    };
  }, {});
  const groupRows = Object.entries(groupTotals);
  const maxGroupImpressions = Math.max(...groupRows.map(([, totals]) => totals.impressions), 1);
  const ctrGauge = Math.min(Math.max(summary?.ctr ?? 0, 0), 100);

  return (
    <MaintenanceGate
      eyebrow="Advertising Maintenance"
      title="Analytics Are Being Updated"
      message="The featured ad analytics area is being updated right now. Admin preview access is available while this advertising area is down."
    >
      <Helmet>
        <title>Ad Analytics | The Blend Battlegrounds</title>
        <meta name="description" content="Review featured advertisement impressions, clicks, and campaign performance." />
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
              Advertising Analytics
            </p>
            <h1
              className="max-w-4xl uppercase leading-none text-white"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 8vw, 6.5rem)' }}
            >
              Campaign Performance
            </h1>
            <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
              Track impressions, clicks, click-through rate, and placement performance for your featured ad campaigns.
            </p>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            {isAnalyticsLoading && (
              <div className="flex min-h-40 items-center justify-center border border-[#2a2a2a] bg-[#080808] text-[#888888]">
                <Loader2 className="mr-3 animate-spin text-primary" size={20} />
                Loading ad analytics
              </div>
            )}

            {!isAnalyticsLoading && error && (
              <div className="border border-primary bg-[#160808] p-4 text-sm leading-6 text-[#dddddd]">{error}</div>
            )}

            {!isAnalyticsLoading && !error && analytics && (
              <div className="grid gap-6">
                <div className="grid gap-4 md:grid-cols-4">
                  {[
                    { label: 'Campaigns', value: summary?.campaigns ?? 0, icon: Radio },
                    { label: 'Impressions', value: summary?.impressions ?? 0, icon: Eye },
                    { label: 'Clicks', value: summary?.clicks ?? 0, icon: MousePointerClick },
                    { label: 'CTR', value: `${summary?.ctr ?? 0}%`, icon: BarChart3 },
                  ].map((item) => {
                    const Icon = item.icon;

                    return (
                      <article key={item.label} className="border border-[#2a2a2a] bg-[#080808] p-5">
                        <Icon className="text-primary" size={22} />
                        <p className="mt-5 text-[10px] font-bold uppercase tracking-widest text-[#777777]">{item.label}</p>
                        <p className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {item.value}
                        </p>
                      </article>
                    );
                  })}
                </div>

                {campaigns.length > 0 && (
                  <div className="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(340px,0.85fr)]">
                    <section className="border border-[#2a2a2a] bg-[#080808] p-5">
                      <div className="mb-5 flex items-center gap-3 border-b border-[#262626] pb-5">
                        <BarChart3 className="text-[#FFB800]" size={22} />
                        <div>
                          <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Campaign Activity</p>
                          <h2 className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                            Impressions vs Clicks
                          </h2>
                        </div>
                      </div>

                      <div className="grid gap-4">
                        {campaigns.map((campaign) => {
                          const impressionWidth = Math.max((campaign.impressions / maxCampaignActivity) * 100, campaign.impressions > 0 ? 6 : 0);
                          const clickWidth = Math.max((campaign.clicks / maxCampaignActivity) * 100, campaign.clicks > 0 ? 6 : 0);

                          return (
                            <article key={campaign.id} className="grid gap-3 border border-[#1f1f1f] bg-[#101010] p-4">
                              <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                  <p className="text-sm font-semibold text-white">{campaignLabel(campaign)}</p>
                                  <p className="mt-1 text-xs uppercase tracking-widest text-[#777777]">
                                    Group {campaign.group} / Slot {campaign.slot_position}
                                  </p>
                                </div>
                                <p className="text-xs font-bold uppercase tracking-widest text-[#FFB800]">{campaign.ctr}% CTR</p>
                              </div>

                              <div className="grid gap-2">
                                <div className="grid grid-cols-[92px_minmax(0,1fr)_48px] items-center gap-3">
                                  <span className="text-[10px] font-bold uppercase tracking-widest text-[#888888]">Impressions</span>
                                  <div className="h-3 bg-[#1c1c1c]">
                                    <div className="h-full bg-[#FFB800]" style={{ width: `${impressionWidth}%` }} />
                                  </div>
                                  <span className="text-right text-xs text-white">{campaign.impressions}</span>
                                </div>
                                <div className="grid grid-cols-[92px_minmax(0,1fr)_48px] items-center gap-3">
                                  <span className="text-[10px] font-bold uppercase tracking-widest text-[#888888]">Clicks</span>
                                  <div className="h-3 bg-[#1c1c1c]">
                                    <div className="h-full bg-primary" style={{ width: `${clickWidth}%` }} />
                                  </div>
                                  <span className="text-right text-xs text-white">{campaign.clicks}</span>
                                </div>
                              </div>
                            </article>
                          );
                        })}
                      </div>
                    </section>

                    <div className="grid gap-6">
                      <section className="border border-[#2a2a2a] bg-[#080808] p-5">
                        <div className="flex items-center justify-between gap-4">
                          <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Engagement</p>
                            <h2 className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                              CTR Meter
                            </h2>
                          </div>
                          <p className="text-4xl text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                            {summary?.ctr ?? 0}%
                          </p>
                        </div>
                        <div className="mt-6 h-5 border border-[#303030] bg-[#101010] p-1">
                          <div className="h-full bg-primary" style={{ width: `${ctrGauge}%` }} />
                        </div>
                        <div className="mt-3 flex justify-between text-[10px] font-bold uppercase tracking-widest text-[#666666]">
                          <span>0%</span>
                          <span>50%</span>
                          <span>100%</span>
                        </div>
                      </section>

                      <section className="border border-[#2a2a2a] bg-[#080808] p-5">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Placement Mix</p>
                        <h2 className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          Group Reach
                        </h2>
                        <div className="mt-5 grid gap-3">
                          {groupRows.map(([group, totals]) => {
                            const width = Math.max((totals.impressions / maxGroupImpressions) * 100, totals.impressions > 0 ? 8 : 0);

                            return (
                              <div key={group} className="grid gap-2">
                                <div className="flex items-center justify-between text-xs">
                                  <span className="font-bold uppercase tracking-widest text-white">{group}</span>
                                  <span className="text-[#888888]">
                                    {totals.impressions} impressions / {totals.clicks} clicks
                                  </span>
                                </div>
                                <div className="h-4 bg-[#1c1c1c]">
                                  <div className="h-full bg-[#FFB800]" style={{ width: `${width}%` }} />
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      </section>
                    </div>
                  </div>
                )}

                <section className="border border-[#2a2a2a] bg-[#080808] p-5">
                  <div className="mb-5 flex items-center gap-3 border-b border-[#262626] pb-5">
                    <BarChart3 className="text-[#FFB800]" size={22} />
                    <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Campaigns
                    </h2>
                  </div>

                  {analytics.campaigns.length === 0 ? (
                    <div className="border border-[#2a2a2a] bg-[#101010] p-6 text-sm leading-6 text-[#888888]">
                      No advertisement analytics are available yet.
                    </div>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full min-w-[780px] border-separate border-spacing-0 text-left">
                        <thead>
                          <tr className="text-[10px] uppercase tracking-widest text-[#777777]">
                            <th className="border-b border-[#262626] px-3 py-3">Campaign</th>
                            <th className="border-b border-[#262626] px-3 py-3">Placement</th>
                            <th className="border-b border-[#262626] px-3 py-3">Status</th>
                            <th className="border-b border-[#262626] px-3 py-3">Dates</th>
                            <th className="border-b border-[#262626] px-3 py-3 text-right">Impressions</th>
                            <th className="border-b border-[#262626] px-3 py-3 text-right">Clicks</th>
                            <th className="border-b border-[#262626] px-3 py-3 text-right">CTR</th>
                          </tr>
                        </thead>
                        <tbody>
                          {analytics.campaigns.map((campaign) => (
                            <tr key={campaign.id} className="text-sm text-[#dddddd]">
                              <td className="border-b border-[#1f1f1f] px-3 py-4">
                                <p className="font-semibold text-white">{campaign.campaign_title || 'Featured Campaign'}</p>
                                <p className="mt-1 text-xs text-[#777777]">{campaign.option_name || 'Campaign option'}</p>
                              </td>
                              <td className="border-b border-[#1f1f1f] px-3 py-4">
                                Group {campaign.group} / Slot {campaign.slot_position}
                              </td>
                              <td className="border-b border-[#1f1f1f] px-3 py-4 capitalize">{campaign.status.replaceAll('_', ' ')}</td>
                              <td className="border-b border-[#1f1f1f] px-3 py-4 text-xs text-[#999999]">
                                {formatDate(campaign.start_date)} - {formatDate(campaign.end_date)}
                              </td>
                              <td className="border-b border-[#1f1f1f] px-3 py-4 text-right">{campaign.impressions}</td>
                              <td className="border-b border-[#1f1f1f] px-3 py-4 text-right">{campaign.clicks}</td>
                              <td className="border-b border-[#1f1f1f] px-3 py-4 text-right">{campaign.ctr}%</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </section>
              </div>
            )}
          </div>
        </section>
      </main>
    </MaintenanceGate>
  );
}

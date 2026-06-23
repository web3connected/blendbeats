import { Helmet } from '@dr.pogodin/react-helmet';
import {
  Activity,
  ArrowRight,
  BadgeCheck,
  BarChart3,
  CheckCircle2,
  Copy,
  Gift,
  History,
  Link2,
  Loader2,
  Mail,
  ShieldCheck,
  UserRound,
  Users,
  Wallet,
} from 'lucide-react';
import { type FormEvent, useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  AffiliateApiError,
  type AffiliateAccount,
  type AffiliatePayoutsResponse,
  type AffiliateReferralsResponse,
  type AffiliateRegistrationOutputs,
  type AffiliateRewardsResponse,
  type AffiliateSummaryResponse,
  getAffiliateReferrals,
  getAffiliatePayouts,
  getAffiliateRewards,
  getAffiliateSummary,
  redeemAffiliateReward,
  registerAffiliateAccount,
  requestAffiliatePayout,
} from '@/lib/affiliate';

import ActivityPanel from './affiliate/ActivityPanel';
import AffiliateMetricGrid from './affiliate/AffiliateMetricGrid';
import { firstError, formatDate, numberLabel, percentLabel, statusLabel, titleLabel } from './affiliate/formatters';

export default function AffiliateProgramPage() {
  const { user, isLoading } = useAuth();
  const [account, setAccount] = useState<AffiliateAccount | null>(null);
  const [summary, setSummary] = useState<AffiliateSummaryResponse | null>(null);
  const [referralsResponse, setReferralsResponse] = useState<AffiliateReferralsResponse | null>(null);
  const [rewardsResponse, setRewardsResponse] = useState<AffiliateRewardsResponse | null>(null);
  const [payoutsResponse, setPayoutsResponse] = useState<AffiliatePayoutsResponse | null>(null);
  const [outputs, setOutputs] = useState<AffiliateRegistrationOutputs | null>(null);
  const [displayName, setDisplayName] = useState('');
  const [contactEmail, setContactEmail] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('paypal');
  const [payoutNotes, setPayoutNotes] = useState('');
  const [isAccountLoading, setIsAccountLoading] = useState(false);
  const [isDashboardLoading, setIsDashboardLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [redeemingRewardId, setRedeemingRewardId] = useState<number | null>(null);
  const [isRequestingPayout, setIsRequestingPayout] = useState(false);
  const [copiedValue, setCopiedValue] = useState('');
  const [error, setError] = useState('');
  const [redeemMessage, setRedeemMessage] = useState('');
  const [payoutMessage, setPayoutMessage] = useState('');

  const loadAffiliateDashboard = useCallback(async () => {
    if (!user) {
      setAccount(null);
      setIsAccountLoading(false);
      setSummary(null);
      setReferralsResponse(null);
      setRewardsResponse(null);
      setPayoutsResponse(null);
      return;
    }

    setIsAccountLoading(true);
    setIsDashboardLoading(false);
    setError('');
    const summaryResponse = await getAffiliateSummary();

    setSummary(summaryResponse);
    setAccount(summaryResponse.affiliate_account);

    if (!summaryResponse.affiliate_account) {
      setDisplayName(user.dj_profile?.dj_name || user.name);
      setContactEmail(user.profile?.contact_email || user.email);
      setReferralsResponse(null);
      setRewardsResponse(null);
      setPayoutsResponse(null);
      setIsAccountLoading(false);
      return;
    }

    setDisplayName(summaryResponse.affiliate_account.display_name);
    setContactEmail(summaryResponse.affiliate_account.contact_email || user.email);
    setIsAccountLoading(false);
    setIsDashboardLoading(true);

    const [referralsData, rewardsData, payoutsData] = await Promise.all([
      getAffiliateReferrals(),
      getAffiliateRewards(),
      summaryResponse.payouts_enabled ? getAffiliatePayouts() : Promise.resolve(null),
    ]);

    setReferralsResponse(referralsData);
    setRewardsResponse(rewardsData);
    setPayoutsResponse(payoutsData);
    setIsDashboardLoading(false);
  }, [user]);

  useEffect(() => {
    let cancelled = false;

    loadAffiliateDashboard().catch((loadError) => {
      if (!cancelled) {
        setError(loadError instanceof AffiliateApiError ? loadError.message : 'Unable to load affiliate status.');
        setIsAccountLoading(false);
        setIsDashboardLoading(false);
      }
    });

    return () => {
      cancelled = true;
    };
  }, [loadAffiliateDashboard]);

  const submitRegistration = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError('');
    setOutputs(null);
    setIsSubmitting(true);

    try {
      const response = await registerAffiliateAccount({
        display_name: displayName,
        contact_email: contactEmail,
      });

      setAccount(response.affiliate_account);
      setOutputs(response.outputs || null);

      if (response.affiliate_account) {
        await loadAffiliateDashboard();
      }
    } catch (submissionError) {
      setError(
        submissionError instanceof AffiliateApiError
          ? firstError(submissionError)
          : 'Unable to join the affiliate program right now.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  const accountRows = account
    ? [
        ['Status', statusLabel(account.status)],
        ['Display Name', account.display_name],
        ['Contact Email', account.contact_email || 'Not Set'],
        ['Joined', formatDate(account.joined_at)],
      ]
    : [];
  const referralStats = summary?.referral_statistics ?? { visits: 0, signups: 0, conversion_rate: 0 };
  const qualificationStats = summary?.qualification_statistics ?? {
    total: 0,
    pending: 0,
    qualified: 0,
    rejected: 0,
    qualification_rate: 0,
  };
  const rewardStats = summary?.reward_statistics ?? {
    total: 0,
    pending: 0,
    approved: 0,
    issued: 0,
    paid: 0,
    redeemed: 0,
    expired: 0,
    cancelled: 0,
    voided: 0,
    membership_credits_available: 0,
    membership_credit_days_available: 0,
    total_amount_cents: 0,
    total_amount_label: '$0.00',
    total_points: 0,
  };
  const payoutsEnabled = summary?.payouts_enabled ?? payoutsResponse?.payouts_enabled ?? false;
  const payoutBalance = payoutsResponse?.balance ?? summary?.payout_balance ?? {
    amount_cents: 0,
    amount_label: '$0.00',
    currency: 'USD',
    reward_count: 0,
    can_request_payout: false,
  };
  const payoutStats = payoutsResponse?.statistics ?? summary?.payout_statistics ?? {
    total: 0,
    requested: 0,
    approved: 0,
    processing: 0,
    paid: 0,
    rejected: 0,
    cancelled: 0,
    total_requested_cents: 0,
    total_paid_cents: 0,
  };
  const referrals = referralsResponse?.referrals ?? [];
  const rewards = rewardsResponse?.rewards ?? [];
  const payouts = payoutsEnabled ? (payoutsResponse?.payouts ?? summary?.payout_history ?? []) : [];
  const referralActivity = summary?.referral_activity ?? [];
  const rewardActivity = rewardsResponse?.activity ?? summary?.reward_activity ?? [];

  const copyText = async (label: string, value: string | null) => {
    if (!value) return;

    await navigator.clipboard?.writeText(value);
    setCopiedValue(label);
    window.setTimeout(() => setCopiedValue(''), 1800);
  };

  const redeemReward = async (rewardId: number) => {
    setError('');
    setRedeemMessage('');
    setRedeemingRewardId(rewardId);

    try {
      const response = await redeemAffiliateReward(rewardId);
      setRedeemMessage(`${response.message} Free membership now expires ${formatDate(response.subscription.expires_at)}.`);
      await loadAffiliateDashboard();
    } catch (redemptionError) {
      setError(
        redemptionError instanceof AffiliateApiError
          ? firstError(redemptionError)
          : 'Unable to redeem that membership credit right now.',
      );
    } finally {
      setRedeemingRewardId(null);
    }
  };

  const submitPayoutRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!payoutsEnabled) return;

    setError('');
    setPayoutMessage('');
    setIsRequestingPayout(true);

    try {
      const response = await requestAffiliatePayout({
        payment_method: paymentMethod,
        notes: payoutNotes,
      });
      setPayoutMessage(`${response.message} ${response.payout.amount_label} is now pending review.`);
      setPayoutNotes('');
      await loadAffiliateDashboard();
    } catch (payoutError) {
      setError(
        payoutError instanceof AffiliateApiError
          ? firstError(payoutError)
          : 'Unable to request that payout right now.',
      );
    } finally {
      setIsRequestingPayout(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Affiliate Program | The Blend Battlegrounds</title>
        <meta name="description" content="Join the BlendBeats affiliate program." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
            <div>
              <p className="mb-3 text-xs font-bold uppercase text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                BlendBeats Affiliate Program
              </p>
              <h1
                className="text-5xl uppercase leading-none text-white sm:text-6xl lg:text-7xl"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Affiliate Registration
              </h1>
              <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                Create your affiliate account, assign your status, and establish the profile BlendBeats will use for future referral tools.
              </p>
            </div>

            <div className="border border-[#303030] bg-[#111111] p-5">
              <div className="mb-4 flex h-12 w-12 items-center justify-center bg-primary text-white">
                <Users size={20} />
              </div>
              <p className="text-xs font-bold uppercase text-[#FFB800]">Step 2</p>
              <p className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                Join Program
              </p>
              <p className="mt-2 text-sm leading-6 text-[#888888]">
                {account ? 'Affiliate account is established.' : 'Affiliate registration is ready.'}
              </p>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
            <aside className="border border-[#2a2a2a] bg-[#111111] p-5">
              <div className="mb-5 flex h-12 w-12 items-center justify-center bg-[#080808] text-primary">
                <UserRound size={20} />
              </div>
              <p className="text-xs font-bold uppercase text-[#777777]">Account Holder</p>
              <p className="mt-3 truncate text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {user?.name || 'Guest'}
              </p>
              <p className="mt-1 break-all text-sm text-[#888888]">{user?.email || 'Sign in required'}</p>
              <div className="mt-6 border-t border-[#262626] pt-5">
                <div className="flex items-center gap-3 text-sm text-[#cccccc]">
                  <ShieldCheck size={16} className="text-primary" />
                  {account ? `Affiliate status: ${statusLabel(account.status)}` : 'Affiliate account not created'}
                </div>
              </div>
            </aside>

            <div className="space-y-6">
              {isLoading || isAccountLoading ? (
                <section className="flex min-h-64 items-center justify-center border border-[#2a2a2a] bg-[#111111] p-6">
                  <Loader2 className="animate-spin text-primary" size={28} />
                </section>
              ) : !user ? (
                <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                  <Mail className="text-primary" size={24} />
                  <h2 className="mt-5 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Sign In To Join
                  </h2>
                  <p className="mt-3 max-w-2xl text-sm leading-6 text-[#aaaaaa]">
                    Affiliate registration is connected to a BlendBeats user account.
                  </p>
                  <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                    <Link
                      to="/register"
                      className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase text-white transition-colors hover:bg-primary/90"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      Create Account
                      <ArrowRight size={16} />
                    </Link>
                    <Link
                      to="/login"
                      className="inline-flex h-12 items-center justify-center border border-[#333333] px-5 text-sm font-bold uppercase text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      Sign In
                    </Link>
                  </div>
                </section>
              ) : account ? (
                <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                  <div className="mb-6 flex items-center gap-3">
                    <BadgeCheck className="text-[#FFB800]" size={24} />
                    <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Affiliate Account Active
                    </h2>
                  </div>

                  {redeemMessage && (
                    <p className="mb-6 border border-[#FFB800]/40 bg-[#FFB800]/10 px-4 py-3 text-sm text-[#FFB800]">
                      {redeemMessage}
                    </p>
                  )}

                  {payoutsEnabled && payoutMessage && (
                    <p className="mb-6 border border-[#FFB800]/40 bg-[#FFB800]/10 px-4 py-3 text-sm text-[#FFB800]">
                      {payoutMessage}
                    </p>
                  )}

                  {error && (
                    <p className="mb-6 border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                      {error}
                    </p>
                  )}

                  {outputs && (
                    <div className="mb-6 grid gap-3 sm:grid-cols-3">
                      <div className="border border-[#303030] bg-[#080808] p-4">
                        <CheckCircle2 className="text-[#FFB800]" size={18} />
                        <p className="mt-3 text-sm font-semibold text-white">Account Created</p>
                        <p className="mt-1 text-xs text-[#888888]">{outputs.affiliate_account_created ? 'Created now' : 'Already exists'}</p>
                      </div>
                      <div className="border border-[#303030] bg-[#080808] p-4">
                        <CheckCircle2 className="text-[#FFB800]" size={18} />
                        <p className="mt-3 text-sm font-semibold text-white">Status Assigned</p>
                        <p className="mt-1 text-xs text-[#888888]">{statusLabel(outputs.affiliate_status_assigned)}</p>
                      </div>
                      <div className="border border-[#303030] bg-[#080808] p-4">
                        <CheckCircle2 className="text-[#FFB800]" size={18} />
                        <p className="mt-3 text-sm font-semibold text-white">Profile Established</p>
                        <p className="mt-1 text-xs text-[#888888]">{outputs.affiliate_profile_established ? 'Complete' : 'Pending'}</p>
                      </div>
                    </div>
                  )}

                  <div className="mb-6 grid gap-4 border border-[#303030] bg-[#080808] p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                    <div>
                      <div className="mb-3 flex items-center gap-2 text-[#FFB800]">
                        <BadgeCheck size={17} />
                        <p className="text-xs font-bold uppercase text-[#FFB800]">Referral Code</p>
                      </div>
                      <div className="flex min-h-12 items-center justify-between gap-3 border border-[#333333] bg-[#111111] px-4">
                        <span className="break-all text-sm font-bold text-white">{account.referral_code || 'Not Set'}</span>
                        <button
                          type="button"
                          onClick={() => copyText('code', account.referral_code)}
                          disabled={!account.referral_code}
                          className="shrink-0 p-2 text-[#aaaaaa] transition-colors hover:text-primary disabled:opacity-40"
                          aria-label="Copy referral code"
                        >
                          <Copy size={16} />
                        </button>
                      </div>
                      {copiedValue === 'code' && <p className="mt-2 text-xs text-[#FFB800]">Referral code copied.</p>}
                    </div>

                    <div>
                      <div className="mb-3 flex items-center gap-2 text-primary">
                        <Link2 size={17} />
                        <p className="text-xs font-bold uppercase text-primary">Referral Link</p>
                      </div>
                      <div className="flex min-h-12 items-center justify-between gap-3 border border-[#333333] bg-[#111111] px-4">
                        <span className="break-all text-sm font-semibold text-[#eeeeee]">{account.referral_link || 'Not Set'}</span>
                        <button
                          type="button"
                          onClick={() => copyText('link', account.referral_link)}
                          disabled={!account.referral_link}
                          className="shrink-0 p-2 text-[#aaaaaa] transition-colors hover:text-primary disabled:opacity-40"
                          aria-label="Copy referral link"
                        >
                          <Copy size={16} />
                        </button>
                      </div>
                      {copiedValue === 'link' && <p className="mt-2 text-xs text-[#FFB800]">Referral link copied.</p>}
                    </div>
                  </div>

                  <dl className="grid gap-4 sm:grid-cols-2">
                    {accountRows.map(([label, value]) => (
                      <div key={label} className="border border-[#303030] bg-[#080808] p-4">
                        <dt className="text-xs font-bold uppercase text-[#777777]">{label}</dt>
                        <dd className="mt-2 break-words text-sm font-semibold text-[#eeeeee]">{value}</dd>
                      </div>
                    ))}
                  </dl>

                  <div className="mt-6 border-t border-[#262626] pt-6">
                    <div className="mb-5 flex items-center gap-3">
                      <BarChart3 className="text-primary" size={20} />
                      <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        Dashboard
                      </h3>
                      {isDashboardLoading && <Loader2 className="animate-spin text-primary" size={18} />}
                    </div>

                    <AffiliateMetricGrid
                      metrics={[
                        { label: 'Visits', value: numberLabel(referralStats.visits), note: `${numberLabel(referralStats.signups)} signups` },
                        { label: 'Conversion', value: percentLabel(referralStats.conversion_rate), note: 'Visits to signups' },
                        { label: 'Qualified', value: numberLabel(qualificationStats.qualified), note: `${numberLabel(qualificationStats.pending)} pending` },
                        { label: 'Rewards', value: numberLabel(rewardStats.total), note: `${numberLabel(rewardStats.pending)} pending` },
                      ]}
                    />

                    <div className="mt-4">
                      <AffiliateMetricGrid
                        valueSize="medium"
                        metrics={[
                          { label: 'Qualification Rate', value: percentLabel(qualificationStats.qualification_rate) },
                          { label: 'Rejected', value: numberLabel(qualificationStats.rejected) },
                          { label: 'Issued Rewards', value: numberLabel(rewardStats.issued) },
                          { label: 'Membership Credits', value: `${numberLabel(rewardStats.membership_credits_available)} available` },
                          ...(payoutsEnabled ? [{ label: 'Payable Balance', value: payoutBalance.amount_label }] : []),
                          { label: 'Reward Points', value: numberLabel(rewardStats.total_points) },
                        ]}
                      />
                    </div>
                  </div>

                  {payoutsEnabled && (
                    <section className="mt-6 border border-[#303030] bg-[#080808] p-4">
                    <div className="mb-4 flex items-center gap-3">
                      <Wallet className="text-[#FFB800]" size={18} />
                      <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        Payouts
                      </h3>
                    </div>

                    <div className="grid gap-3 lg:grid-cols-[260px_minmax(0,1fr)]">
                      <div className="border border-[#303030] bg-[#111111] p-4">
                        <p className="text-xs font-bold uppercase text-[#777777]">Payable Balance</p>
                        <p className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {payoutBalance.amount_label}
                        </p>
                        <p className="mt-2 text-xs text-[#888888]">{numberLabel(payoutBalance.reward_count)} approved rewards</p>
                        <div className="mt-4 grid gap-2 text-xs text-[#aaaaaa]">
                          <p>{numberLabel(payoutStats.requested)} requested</p>
                          <p>{numberLabel(payoutStats.processing)} processing</p>
                          <p>{numberLabel(payoutStats.paid)} paid</p>
                        </div>
                      </div>

                      <form onSubmit={submitPayoutRequest} className="border border-[#303030] bg-[#111111] p-4">
                        <div className="grid gap-4 sm:grid-cols-[180px_minmax(0,1fr)_auto] sm:items-end">
                          <div>
                            <label htmlFor="affiliate-payout-method" className="text-xs font-bold uppercase text-[#bbbbbb]">
                              Method
                            </label>
                            <select
                              id="affiliate-payout-method"
                              value={paymentMethod}
                              onChange={(event) => setPaymentMethod(event.target.value)}
                              className="mt-2 h-11 w-full border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none transition-colors focus:border-primary"
                            >
                              <option value="paypal">PayPal</option>
                              <option value="cashapp">Cash App</option>
                              <option value="manual">Manual</option>
                            </select>
                          </div>
                          <div>
                            <label htmlFor="affiliate-payout-notes" className="text-xs font-bold uppercase text-[#bbbbbb]">
                              Notes
                            </label>
                            <input
                              id="affiliate-payout-notes"
                              type="text"
                              value={payoutNotes}
                              onChange={(event) => setPayoutNotes(event.target.value)}
                              className="mt-2 h-11 w-full border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
                              placeholder="Account handle or processing notes"
                            />
                          </div>
                          <button
                            type="submit"
                            disabled={!payoutBalance.can_request_payout || isRequestingPayout}
                            className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase text-white transition-colors hover:bg-primary/90 disabled:opacity-50"
                            style={{ fontFamily: 'var(--font-heading)' }}
                          >
                            {isRequestingPayout ? 'Requesting...' : 'Request Payout'}
                            {isRequestingPayout ? <Loader2 size={14} className="animate-spin" /> : <ArrowRight size={14} />}
                          </button>
                        </div>

                        <div className="mt-4 grid gap-2">
                          {payouts.slice(0, 4).map((payout) => (
                            <div key={payout.id} className="flex flex-col gap-1 border border-[#303030] bg-[#080808] p-3 sm:flex-row sm:items-center sm:justify-between">
                              <div>
                                <p className="text-sm font-semibold text-white">{payout.amount_label}</p>
                                <p className="text-xs text-[#888888]">{formatDate(payout.requested_at)} | {titleLabel(payout.payment_method)}</p>
                              </div>
                              <span className="w-fit border border-[#444444] px-2 py-1 text-xs font-bold uppercase text-[#FFB800]">
                                {titleLabel(payout.status)}
                              </span>
                            </div>
                          ))}
                          {payouts.length === 0 && <p className="text-sm leading-6 text-[#888888]">No payout requests yet.</p>}
                        </div>
                      </form>
                    </div>
                    </section>
                  )}

                  <div className="mt-6 grid gap-6 xl:grid-cols-2">
                    <section className="border border-[#303030] bg-[#080808] p-4">
                      <div className="mb-4 flex items-center gap-3">
                        <Activity className="text-[#FFB800]" size={18} />
                        <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          Referrals
                        </h3>
                      </div>

                      {referrals.length > 0 ? (
                        <div className="grid gap-3">
                          {referrals.slice(0, 6).map((referral) => (
                            <article key={referral.id} className="border border-[#303030] bg-[#111111] p-4">
                              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                  <p className="text-sm font-semibold text-white">
                                    {referral.referred_user?.name || referral.referred_user?.email || 'Referred user'}
                                  </p>
                                  <p className="mt-1 text-xs text-[#888888]">{referral.referred_user?.email || 'Email not available'}</p>
                                </div>
                                <span className="w-fit border border-[#444444] px-2 py-1 text-xs font-bold uppercase text-[#FFB800]">
                                  {titleLabel(referral.status)}
                                </span>
                              </div>
                              <dl className="mt-3 grid gap-2 text-xs text-[#aaaaaa] sm:grid-cols-2">
                                <div>
                                  <dt className="uppercase text-[#666666]">Attributed</dt>
                                  <dd className="mt-1">{formatDate(referral.attributed_at)}</dd>
                                </div>
                                <div>
                                  <dt className="uppercase text-[#666666]">Qualified</dt>
                                  <dd className="mt-1">{formatDate(referral.qualified_at)}</dd>
                                </div>
                              </dl>
                            </article>
                          ))}
                        </div>
                      ) : (
                        <p className="text-sm leading-6 text-[#888888]">No referrals yet.</p>
                      )}
                    </section>

                    <section className="border border-[#303030] bg-[#080808] p-4">
                      <div className="mb-4 flex items-center gap-3">
                        <Gift className="text-primary" size={18} />
                        <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          Rewards
                        </h3>
                      </div>

                      {rewards.length > 0 ? (
                        <div className="grid gap-3">
                          {rewards.slice(0, 6).map((reward) => (
                            <article key={reward.id} className="border border-[#303030] bg-[#111111] p-4">
                              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                  <p className="text-sm font-semibold text-white">{titleLabel(reward.reward_type)}</p>
                                  <p className="mt-1 text-xs text-[#888888]">
                                    {reward.reward_type === 'membership_credit'
                                      ? `${numberLabel(reward.membership_credit_days || 0)} days of DJ Plus`
                                      : reward.amount_label || (reward.points ? `${numberLabel(reward.points)} points` : `Quantity ${reward.quantity}`)}
                                  </p>
                                </div>
                                <span className="w-fit border border-[#444444] px-2 py-1 text-xs font-bold uppercase text-primary">
                                  {titleLabel(reward.status)}
                                </span>
                              </div>
                              <dl className="mt-3 grid gap-2 text-xs text-[#aaaaaa] sm:grid-cols-2">
                                <div>
                                  <dt className="uppercase text-[#666666]">Available</dt>
                                  <dd className="mt-1">{formatDate(reward.available_at)}</dd>
                                </div>
                                <div>
                                  <dt className="uppercase text-[#666666]">Issued</dt>
                                  <dd className="mt-1">{formatDate(reward.issued_at)}</dd>
                                </div>
                                {reward.reward_type === 'membership_credit' && (
                                  <>
                                    <div>
                                      <dt className="uppercase text-[#666666]">Redeem By</dt>
                                      <dd className="mt-1">{formatDate(reward.expires_at)}</dd>
                                    </div>
                                    <div>
                                      <dt className="uppercase text-[#666666]">Redeemed</dt>
                                      <dd className="mt-1">{formatDate(reward.redeemed_at)}</dd>
                                    </div>
                                  </>
                                )}
                              </dl>
                              {reward.reward_type === 'membership_credit' && (
                                <div className="mt-4 flex flex-col gap-2 border-t border-[#262626] pt-3 sm:flex-row sm:items-center sm:justify-between">
                                  <p className="text-xs text-[#888888]">
                                    {reward.can_redeem
                                      ? 'Available membership credit.'
                                      : reward.is_expired
                                        ? 'This credit expired unused.'
                                        : reward.redeemed_at
                                          ? `Applied through ${formatDate(reward.redeemed_membership_expires_at)}.`
                                          : 'This credit is not available.'}
                                  </p>
                                  {reward.can_redeem && (
                                    <button
                                      type="button"
                                      onClick={() => redeemReward(reward.id)}
                                      disabled={redeemingRewardId === reward.id}
                                      className="inline-flex h-10 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase text-white transition-colors hover:bg-primary/90 disabled:opacity-60"
                                      style={{ fontFamily: 'var(--font-heading)' }}
                                    >
                                      {redeemingRewardId === reward.id ? 'Redeeming...' : 'Redeem Credit'}
                                      {redeemingRewardId === reward.id ? <Loader2 size={14} className="animate-spin" /> : <ArrowRight size={14} />}
                                    </button>
                                  )}
                                </div>
                              )}
                            </article>
                          ))}
                        </div>
                      ) : (
                        <p className="text-sm leading-6 text-[#888888]">No rewards yet.</p>
                      )}
                    </section>
                  </div>

                  <div className="mt-6 grid gap-6 xl:grid-cols-2">
                    <ActivityPanel
                      emptyText="No referral activity yet."
                      icon={History}
                      iconClassName="text-[#FFB800]"
                      items={referralActivity}
                      title="Referral Activity"
                    />
                    <ActivityPanel
                      emptyText="No reward activity yet."
                      icon={History}
                      iconClassName="text-primary"
                      items={rewardActivity}
                      title="Reward Activity"
                    />
                  </div>
                </section>
              ) : (
                <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                  <div className="mb-6 flex items-center gap-3">
                    <BadgeCheck className="text-primary" size={24} />
                    <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Create Affiliate Account
                    </h2>
                  </div>

                  <form onSubmit={submitRegistration} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                      <div>
                        <label htmlFor="affiliate-display-name" className="text-xs font-bold uppercase text-[#bbbbbb]">
                          Affiliate Display Name
                        </label>
                        <input
                          id="affiliate-display-name"
                          type="text"
                          value={displayName}
                          onChange={(event) => setDisplayName(event.target.value)}
                          required
                          className="mt-2 h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
                          placeholder="DJ name or full name"
                        />
                      </div>

                      <div>
                        <label htmlFor="affiliate-contact-email" className="text-xs font-bold uppercase text-[#bbbbbb]">
                          Contact Email
                        </label>
                        <input
                          id="affiliate-contact-email"
                          type="email"
                          value={contactEmail}
                          onChange={(event) => setContactEmail(event.target.value)}
                          required
                          className="mt-2 h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
                          placeholder="you@example.com"
                        />
                      </div>
                    </div>

                    {error && (
                      <p className="border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                        {error}
                      </p>
                    )}

                    <button
                      type="submit"
                      disabled={isSubmitting}
                      className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase text-white transition-colors hover:bg-primary/90 disabled:opacity-60 sm:w-fit"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      {isSubmitting ? 'Creating Account...' : 'Join Affiliate Program'}
                      {isSubmitting ? <Loader2 size={16} className="animate-spin" /> : <ArrowRight size={16} />}
                    </button>
                  </form>
                </section>
              )}
            </div>
          </div>
        </section>
      </main>
    </>
  );
}

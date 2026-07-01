import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowRight, History, LockKeyhole, Plus, ShieldCheck, WalletCards } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { getWallet, type WalletResponse, type WalletTransaction } from '@/lib/wallet';

function formatTokens(value: number): string {
  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 0,
  }).format(value);
}

function formatDate(value: string | null): string {
  if (!value) return 'Pending';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(value));
}

function formatLabel(value: string): string {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function directionTone(direction: string): string {
  if (direction === 'credit' || direction === 'unlock') return 'text-[#22c55e]';
  if (direction === 'lock') return 'text-[#FFB800]';
  return 'text-primary';
}

function signedAmount(transaction: WalletTransaction): string {
  if (transaction.direction === 'adjustment') {
    const before = transaction.balance_before + transaction.locked_balance_before;
    const after = transaction.balance_after + transaction.locked_balance_after;
    const delta = after - before;
    const sign = delta > 0 ? '+' : delta < 0 ? '-' : '';

    return `${sign}${formatTokens(Math.abs(delta))}`;
  }

  const sign = transaction.direction === 'credit' || transaction.direction === 'unlock' ? '+' : '-';
  return `${sign}${formatTokens(transaction.amount)}`;
}

export default function WalletPage() {
  const { user, isLoading: isAuthLoading } = useAuth();
  const [data, setData] = useState<WalletResponse | null>(null);
  const [isWalletLoading, setIsWalletLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!user) {
      setIsWalletLoading(false);
      return;
    }

    let cancelled = false;
    setIsWalletLoading(true);
    setError('');

    getWallet()
      .then((response) => {
        if (!cancelled) setData(response);
      })
      .catch((loadError) => {
        if (!cancelled) setError(loadError instanceof Error ? loadError.message : 'Wallet could not be loaded.');
      })
      .finally(() => {
        if (!cancelled) setIsWalletLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [user]);

  const wallet = data?.wallet;
  const demoMode = data?.demo_mode;
  const tokenLabel = demoMode?.token_label ?? 'Tokens';
  const transactions = data?.transactions ?? [];
  const balanceCards = useMemo(() => [
    {
      label: `Available ${tokenLabel}`,
      value: wallet ? formatTokens(wallet.available_balance) : '0',
      icon: WalletCards,
      tone: 'text-primary',
    },
    {
      label: `Locked ${tokenLabel}`,
      value: wallet ? formatTokens(wallet.locked_balance) : '0',
      icon: LockKeyhole,
      tone: 'text-[#FFB800]',
    },
    {
      label: 'Lifetime Earned',
      value: wallet ? formatTokens(wallet.lifetime_earned) : '0',
      icon: Plus,
      tone: 'text-[#22c55e]',
    },
  ], [tokenLabel, wallet]);

  if (isAuthLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20 text-white">
        <div className="container mx-auto max-w-6xl">
          <div className="h-48 animate-pulse bg-[#141414]" />
        </div>
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  return (
    <>
      <Helmet>
        <title>Wallet | The Blend Battlegrounds</title>
        <meta name="description" content="Review your BlendBeat token balance and wallet ledger." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] text-white">
        <section className="border-b border-[#1f1f1f] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-end">
              <div>
                <p className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                  Account / Wallet
                </p>
                <h1
                  className="uppercase leading-none text-white"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.75rem, 8vw, 6.75rem)' }}
                >
                  BlendBeat Wallet
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  {demoMode?.enabled
                    ? 'These are test tokens for beta battles. They cannot be withdrawn or converted to real money.'
                    : 'Tokens power battle entries, rewards, promotions, purchases, refunds, and future withdrawals.'}
                </p>
              </div>

              <div className="border border-[#303030] bg-[#111111] p-5">
                <div className="mb-5 flex h-12 w-12 items-center justify-center bg-primary text-white">
                  <ShieldCheck size={20} />
                </div>
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">
                  {demoMode?.enabled ? 'Demo Balance' : 'Total Balance'}
                </p>
                <p className="mt-2 text-5xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {wallet ? formatTokens(wallet.total_balance) : '0'}
                </p>
                <p className="mt-2 text-sm text-[#888888]">
                  {tokenLabel} / Status: {wallet ? formatLabel(wallet.status) : 'Loading'}
                </p>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            {isWalletLoading && (
              <div className="grid gap-4 md:grid-cols-3">
                {[0, 1, 2].map((item) => (
                  <div key={item} className="h-32 animate-pulse border border-[#222222] bg-[#111111]" />
                ))}
              </div>
            )}

            {!isWalletLoading && error && (
              <div className="border border-primary bg-[#160808] p-4 text-sm leading-6 text-[#dddddd]">{error}</div>
            )}

            {!isWalletLoading && !error && (
              <>
                {demoMode?.enabled && (
                  <div className="mb-6 border border-primary/50 bg-primary/10 p-5">
                    <p className="text-[11px] font-bold uppercase tracking-widest text-primary">Beta Demo Mode Active</p>
                    <p className="mt-2 text-sm leading-6 text-[#eeeeee]">
                      {demoMode.withdrawals_disabled_message}
                    </p>
                  </div>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                  {balanceCards.map((card) => {
                    const Icon = card.icon;

                    return (
                      <div key={card.label} className="border border-[#2a2a2a] bg-[#111111] p-5">
                        <Icon className={card.tone} size={22} />
                        <p className="mt-5 text-[11px] font-bold uppercase tracking-widest text-[#888888]">{card.label}</p>
                        <p className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {card.value}
                        </p>
                      </div>
                    );
                  })}
                </div>

                <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
                  <section>
                    <div className="mb-4 flex items-center justify-between gap-4">
                      <div>
                        <p className="text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                          Ledger
                        </p>
                        <h2 className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          Recent Activity
                        </h2>
                      </div>
                      <History className="text-[#777777]" size={22} />
                    </div>

                    {transactions.length === 0 ? (
                      <div className="border border-[#2a2a2a] bg-[#111111] p-8 text-center">
                        <WalletCards className="mx-auto text-primary" size={34} />
                        <h3 className="mt-5 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          No Transactions Yet
                        </h3>
                        <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-[#888888]">
                          Battle locks, rewards, admin grants, refunds, and beta reward simulations will appear here.
                        </p>
                      </div>
                    ) : (
                      <div className="grid gap-3">
                        {transactions.map((transaction) => (
                          <article
                            key={transaction.uuid}
                            className="grid gap-4 border border-[#2a2a2a] bg-[#111111] p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                          >
                            <div>
                              <div className="flex flex-wrap items-center gap-2">
                                <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                                  {formatLabel(transaction.type)}
                                </h3>
                                <span className="text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
                                  {formatLabel(transaction.status)}
                                </span>
                              </div>
                              <p className="mt-1 text-sm text-[#888888]">
                                {transaction.description || formatLabel(transaction.direction)}
                              </p>
                              <p className="mt-2 text-xs text-[#666666]">{formatDate(transaction.created_at)}</p>
                            </div>
                            <p className={`text-3xl uppercase ${directionTone(transaction.direction)}`} style={{ fontFamily: 'var(--font-heading)' }}>
                              {signedAmount(transaction)}
                            </p>
                          </article>
                        ))}
                      </div>
                    )}
                  </section>

                  <aside className="border border-[#2a2a2a] bg-[#111111] p-5">
                    <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Battle Ready</p>
                    <h2 className="mt-3 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      {demoMode?.enabled ? 'Beta Token Demo Mode' : 'Wallet Escrow Foundation'}
                    </h2>
                    <p className="mt-4 text-sm leading-6 text-[#aaaaaa]">
                      {demoMode?.enabled
                        ? 'Test-token stakes lock from your demo balance, then settle as beta winner rewards, fan rewards, or refunds.'
                        : 'Battle stakes will lock from available balance, stay visible here, then release as rewards or refunds after results.'}
                    </p>
                    <Link
                      to="/battles"
                      className="mt-6 inline-flex h-11 items-center justify-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      View Battles
                      <ArrowRight size={15} />
                    </Link>
                  </aside>
                </div>
              </>
            )}
          </div>
        </section>
      </main>
    </>
  );
}

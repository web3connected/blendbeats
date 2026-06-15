import {
  ArrowRight,
  CreditCard,
  ExternalLink,
  Loader2,
  Minus,
  PackageCheck,
  Plus,
  ShoppingBag,
  ShoppingCart,
  Trash2,
  X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import {
  CommerceCart,
  fetchCommerceCart,
  removeCommerceCartItem,
  updateCommerceCartItem,
} from '@/lib/commerce';

export default function HeaderCartDrawer() {
  const [cart, setCart] = useState<CommerceCart | null>(null);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [busyItem, setBusyItem] = useState<number | null>(null);
  const [error, setError] = useState('');

  const refreshCart = () => {
    setIsLoading(true);
    fetchCommerceCart()
      .then((nextCart) => {
        setCart(nextCart);
        setError('');
      })
      .catch(() => setError('Cart could not be loaded.'))
      .finally(() => setIsLoading(false));
  };

  useEffect(() => {
    refreshCart();

    const handleCartUpdated = (event: Event) => {
      const detail = (event as CustomEvent<CommerceCart>).detail;
      if (detail) {
        setCart(detail);
      } else {
        refreshCart();
      }
    };

    window.addEventListener('commerce-cart-updated', handleCartUpdated);
    return () => window.removeEventListener('commerce-cart-updated', handleCartUpdated);
  }, []);

  useEffect(() => {
    if (isOpen) refreshCart();
  }, [isOpen]);

  async function updateQuantity(itemId: number, quantity: number) {
    setBusyItem(itemId);
    setError('');

    try {
      const nextCart = quantity <= 0
        ? await removeCommerceCartItem(itemId)
        : await updateCommerceCartItem(itemId, quantity);

      setCart(nextCart);
    } catch (cartError) {
      setError(cartError instanceof Error ? cartError.message : 'Cart could not be updated.');
    } finally {
      setBusyItem(null);
    }
  }

  const itemCount = cart?.item_count ?? 0;
  const checkoutGroups = Object.entries(cart?.checkout_groups ?? {});
  const externalItemCount = cart?.items
    .filter((item) => item.external_checkout_required)
    .reduce((total, item) => total + item.quantity, 0) ?? 0;
  const internalItemCount = itemCount - externalItemCount;

  return (
    <>
      <button
        type="button"
        onClick={() => setIsOpen(true)}
        className="relative inline-flex h-10 w-10 items-center justify-center border border-[#333333] bg-[#111111] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
        aria-label={`Open cart${itemCount ? `, ${itemCount} item${itemCount === 1 ? '' : 's'}` : ''}`}
      >
        <ShoppingCart size={17} />
        {itemCount > 0 && (
          <span className="absolute -right-1 -top-1 h-5 min-w-5 bg-primary px-1 text-center text-[10px] font-bold leading-5 text-white">
            {Math.min(itemCount, 99)}
          </span>
        )}
      </button>

      {isOpen && (
        <div className="fixed inset-0 z-[90]">
          <button
            type="button"
            className="absolute inset-0 bg-black/80 backdrop-blur-sm"
            aria-label="Close cart"
            onClick={() => setIsOpen(false)}
          />

          <aside className="absolute right-0 top-0 flex h-[100dvh] w-full max-w-[520px] flex-col border-l border-[#303030] bg-[#070707] shadow-2xl shadow-black">
            <div className="border-b border-[#242424] bg-[#0d0d0d] p-5">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-xs uppercase tracking-widest text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>
                    Routing Cart
                  </p>
                  <h2 className="mt-1 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {cart?.estimated_total_label || '$0.00'}
                  </h2>
                  <p className="mt-2 text-sm text-[#999999]">
                    {itemCount} item{itemCount === 1 ? '' : 's'} split across {checkoutGroups.length || 0} checkout route{checkoutGroups.length === 1 ? '' : 's'}.
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setIsOpen(false)}
                  className="inline-flex h-11 w-11 shrink-0 items-center justify-center border border-[#333333] bg-[#090909] text-[#dddddd] hover:border-primary hover:text-primary"
                  aria-label="Close cart"
                >
                  <X size={18} />
                </button>
              </div>

              <div className="mt-5 grid grid-cols-3 border border-[#262626] bg-[#090909]">
                <div className="border-r border-[#262626] p-3">
                  <p className="text-2xl text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>{itemCount}</p>
                  <p className="mt-1 text-[10px] uppercase tracking-widest text-[#777777]">Items</p>
                </div>
                <div className="border-r border-[#262626] p-3">
                  <p className="text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>{internalItemCount}</p>
                  <p className="mt-1 text-[10px] uppercase tracking-widest text-[#777777]">Platform</p>
                </div>
                <div className="p-3">
                  <p className="text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>{externalItemCount}</p>
                  <p className="mt-1 text-[10px] uppercase tracking-widest text-[#777777]">Partner</p>
                </div>
              </div>
            </div>

            {error && <div className="mx-5 mt-5 border border-primary bg-primary/10 p-3 text-sm text-white">{error}</div>}

            <div className="flex-1 overflow-y-auto bg-[#070707] p-5">
              {isLoading && !cart ? (
                <div className="flex h-40 items-center justify-center border border-[#292929] bg-[#101010] text-sm text-[#888888]">
                  <Loader2 className="mr-2 animate-spin text-primary" size={16} />
                  Loading cart
                </div>
              ) : !cart || cart.items.length === 0 ? (
                <div className="grid gap-5">
                  <div className="border border-[#292929] bg-[#101010] p-6 text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center border border-[#333333] bg-[radial-gradient(circle_at_55%_30%,rgba(255,184,0,0.22),transparent_45%),linear-gradient(135deg,#1b1b1b,#070707)]">
                      <ShoppingBag className="text-[#ffb800]" size={28} />
                    </div>
                    <h3 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Your Cart Is Empty
                    </h3>
                    <p className="mt-3 text-sm leading-6 text-[#a9a9a9]">
                      Add products to route them between BlendBeats checkout, partner redirects, vendor checkout, and print-on-demand fulfillment.
                    </p>
                  </div>
                  <a
                    href="/merch"
                    onClick={() => setIsOpen(false)}
                    className="flex h-12 items-center justify-center gap-2 bg-primary text-xs font-bold uppercase tracking-widest text-white"
                    style={{ fontFamily: 'var(--font-heading)' }}
                  >
                    Browse Products <ArrowRight size={15} />
                  </a>
                </div>
              ) : (
                <div className="space-y-5">
                  {checkoutGroups.map(([key, group]) => (
                    <section key={key} className="overflow-hidden border border-[#303030] bg-[#101010]">
                      <div className="flex items-start justify-between gap-4 border-b border-[#2a2a2a] bg-[#151515] p-4">
                        <div className="flex items-start gap-3">
                          <span className="flex h-10 w-10 shrink-0 items-center justify-center border border-[#333333] bg-[#090909] text-[#ffb800]">
                            {key.includes('affiliate') || key.includes('vendor') || key.includes('marketplace') ? <ExternalLink size={17} /> : <PackageCheck size={17} />}
                          </span>
                          <div>
                            <p className="text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                              {group.label}
                            </p>
                            <p className="mt-1 text-xs uppercase tracking-widest text-[#888888]">{group.item_count} items</p>
                          </div>
                        </div>
                        <p className="text-xl text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>{group.total_label}</p>
                      </div>

                      <div className="divide-y divide-[#282828]">
                        {group.items.map((item) => (
                          <div key={item.id} className="grid gap-4 p-4">
                            <div className="flex gap-4">
                              <div className="relative h-20 w-20 shrink-0 overflow-hidden border border-[#303030] bg-[radial-gradient(circle_at_55%_30%,rgba(255,184,0,0.18),transparent_42%),linear-gradient(135deg,#1b1b1b,#070707)]">
                                {item.image_url ? (
                                  <img src={item.image_url} alt={item.title} className="h-full w-full object-cover" />
                                ) : (
                                  <ShoppingBag className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-[#ffb800]/70" size={28} />
                                )}
                              </div>
                              <div className="min-w-0 flex-1">
                                <div className="flex items-start justify-between gap-3">
                                  <div className="min-w-0">
                                    <p className="truncate font-bold text-white">{item.title}</p>
                                    <p className="mt-1 text-xs uppercase tracking-widest text-[#888888]">{item.vendor_name || 'BlendBeats'}</p>
                                    <p className="mt-2 text-xs text-[#777777]">{item.fulfillment_type.replaceAll('_', ' ')}</p>
                                  </div>
                                  <div className="text-right">
                                    <p className="text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>{item.estimated_total_label}</p>
                                    <p className="mt-1 text-xs text-[#777777]">{item.unit_price_label} each</p>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <div className="flex flex-wrap items-center justify-between gap-3">
                              <div className="flex h-10 items-center border border-[#333333] bg-[#090909]">
                                <button className="flex h-full w-10 items-center justify-center text-[#dddddd] hover:text-primary disabled:cursor-wait disabled:opacity-60" type="button" onClick={() => updateQuantity(item.id, item.quantity - 1)} disabled={busyItem === item.id}>
                                  <Minus size={14} />
                                </button>
                                <span className="min-w-12 text-center text-sm font-bold text-white">{item.quantity}</span>
                                <button className="flex h-full w-10 items-center justify-center text-[#dddddd] hover:text-primary disabled:cursor-wait disabled:opacity-60" type="button" onClick={() => updateQuantity(item.id, item.quantity + 1)} disabled={busyItem === item.id}>
                                  <Plus size={14} />
                                </button>
                              </div>

                              <div className="flex flex-wrap items-center gap-2">
                                {item.external_checkout_required && item.affiliate_tracking_url && (
                                  <a className="inline-flex h-10 items-center gap-2 border border-[#3a3a3a] px-3 text-xs font-bold uppercase tracking-widest text-[#dddddd] hover:border-[#ffb800] hover:text-[#ffb800]" href={item.affiliate_tracking_url} target="_blank" rel="noreferrer">
                                    Partner <ExternalLink size={13} />
                                  </a>
                                )}
                                <button className="inline-flex h-10 items-center gap-2 border border-[#3a3a3a] px-3 text-xs font-bold uppercase tracking-widest text-[#dddddd] hover:border-primary hover:text-primary disabled:cursor-wait disabled:opacity-60" type="button" onClick={() => updateQuantity(item.id, 0)} disabled={busyItem === item.id}>
                                  <Trash2 size={13} /> Remove
                                </button>
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    </section>
                  ))}
                </div>
              )}
            </div>

            <div className="border-t border-[#242424] bg-[#0d0d0d] p-5">
              <div className="mb-4 grid gap-2 text-sm">
                <div className="flex items-center justify-between text-[#aaaaaa]">
                  <span>Estimated subtotal</span>
                  <strong className="text-white">{cart?.estimated_total_label || '$0.00'}</strong>
                </div>
                <div className="flex items-center justify-between text-[#777777]">
                  <span>Shipping / partner fees</span>
                  <span>Calculated by route</span>
                </div>
                <div className="flex items-center justify-between text-[#777777]">
                  <span>Taxes</span>
                  <span>Calculated at checkout</span>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <a
                  href="/merch"
                  onClick={() => setIsOpen(false)}
                  className="flex h-12 items-center justify-center border border-[#333333] text-xs font-bold uppercase tracking-widest text-[#dddddd] hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Keep Shopping
                </a>
                <button
                  type="button"
                  className="flex h-12 items-center justify-center gap-2 bg-primary text-xs font-bold uppercase tracking-widest text-white disabled:opacity-50"
                  style={{ fontFamily: 'var(--font-heading)' }}
                  disabled={!cart || cart.items.length === 0}
                >
                  <CreditCard size={15} />
                  Checkout Preview
                </button>
              </div>

              {cart && cart.items.length > 0 && (
                <p className="mt-3 text-xs leading-5 text-[#888888]">
                  Internal products stay on BlendBeats. Affiliate and vendor products open the partner checkout route.
                </p>
              )}
            </div>
          </aside>
        </div>
      )}
    </>
  );
}

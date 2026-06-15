import { ExternalLink, Loader2, Minus, Plus, ShoppingCart, Trash2, X } from 'lucide-react';
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
        <div className="fixed inset-0 z-[80]">
          <button
            type="button"
            className="absolute inset-0 bg-black/70"
            aria-label="Close cart"
            onClick={() => setIsOpen(false)}
          />
          <aside className="absolute right-0 top-0 flex h-full w-full max-w-[440px] flex-col border-l border-[#2a2a2a] bg-[#0b0b0b] shadow-2xl shadow-black">
            <div className="flex items-center justify-between border-b border-[#242424] p-5">
              <div>
                <p className="text-xs uppercase tracking-widest text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>
                  Routing Cart
                </p>
                <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {cart?.estimated_total_label || '$0.00'}
                </h2>
              </div>
              <button
                type="button"
                onClick={() => setIsOpen(false)}
                className="inline-flex h-10 w-10 items-center justify-center border border-[#333333] text-[#dddddd] hover:border-primary hover:text-primary"
                aria-label="Close cart"
              >
                <X size={18} />
              </button>
            </div>

            {error && <div className="m-5 border border-primary bg-primary/10 p-3 text-sm text-white">{error}</div>}

            <div className="flex-1 overflow-y-auto p-5">
              {isLoading && !cart ? (
                <div className="flex h-40 items-center justify-center text-sm text-[#888888]">
                  <Loader2 className="mr-2 animate-spin text-primary" size={16} />
                  Loading cart
                </div>
              ) : !cart || cart.items.length === 0 ? (
                <div className="border border-[#292929] p-5 text-sm leading-6 text-[#a9a9a9]">
                  Add products to see how checkout splits between BlendBeats, partner redirects, vendor checkout, and print-on-demand routes.
                </div>
              ) : (
                <div className="space-y-5">
                  {Object.entries(cart.checkout_groups).map(([key, group]) => (
                    <section key={key} className="border border-[#292929] bg-[#101010]">
                      <div className="border-b border-[#292929] p-4">
                        <p className="text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {group.label}
                        </p>
                        <p className="mt-1 text-xs uppercase tracking-widest text-[#888888]">{group.item_count} items / {group.total_label}</p>
                      </div>
                      <div className="divide-y divide-[#292929]">
                        {group.items.map((item) => (
                          <div key={item.id} className="p-4">
                            <div className="flex items-start justify-between gap-3">
                              <div>
                                <p className="font-bold text-white">{item.title}</p>
                                <p className="mt-1 text-xs uppercase tracking-widest text-[#888888]">{item.vendor_name || 'BlendBeats'}</p>
                              </div>
                              <p className="text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>{item.estimated_total_label}</p>
                            </div>
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                              <div className="flex items-center border border-[#333333]">
                                <button className="p-2" type="button" onClick={() => updateQuantity(item.id, item.quantity - 1)} disabled={busyItem === item.id}>
                                  <Minus size={14} />
                                </button>
                                <span className="min-w-10 text-center text-sm text-white">{item.quantity}</span>
                                <button className="p-2" type="button" onClick={() => updateQuantity(item.id, item.quantity + 1)} disabled={busyItem === item.id}>
                                  <Plus size={14} />
                                </button>
                              </div>
                              <div className="flex items-center gap-2">
                                {item.external_checkout_required && item.affiliate_tracking_url && (
                                  <a className="inline-flex items-center gap-2 border border-[#333333] px-3 py-2 text-xs uppercase tracking-widest text-[#dddddd]" href={item.affiliate_tracking_url} target="_blank" rel="noreferrer">
                                    Partner <ExternalLink size={13} />
                                  </a>
                                )}
                                <button className="inline-flex items-center gap-2 border border-[#333333] px-3 py-2 text-xs uppercase tracking-widest text-[#dddddd]" type="button" onClick={() => updateQuantity(item.id, 0)} disabled={busyItem === item.id}>
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

            <div className="border-t border-[#242424] p-5">
              <button
                type="button"
                className="flex h-12 w-full items-center justify-center bg-primary text-xs font-bold uppercase tracking-widest text-white"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Checkout Routing Coming Soon
              </button>
            </div>
          </aside>
        </div>
      )}
    </>
  );
}

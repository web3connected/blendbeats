import { useEffect, useMemo, useState } from 'react';
import { ExternalLink, Loader2, Minus, Plus, Route, ShoppingBag, Trash2 } from 'lucide-react';

import HeaderTitle from '@/layouts/HeaderTitle';
import {
  addCommerceCartItem,
  CommerceCart,
  CommerceProduct,
  fetchCommerceCart,
  fetchCommerceProducts,
  removeCommerceCartItem,
  updateCommerceCartItem,
} from '@/lib/commerce';

function ProductArtwork({ product }: { product: CommerceProduct }) {
  if (product.image_url) {
    return (
      <img
        src={product.image_url}
        alt={`${product.title} product image`}
        loading="lazy"
        className="absolute inset-0 h-full w-full object-cover"
      />
    );
  }

  return (
    <div className="absolute inset-0 bg-[radial-gradient(circle_at_55%_30%,rgba(255,184,0,0.22),transparent_35%),linear-gradient(135deg,#1b1b1b,#070707)]">
      <ShoppingBag
        size={64}
        className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-[#FFB800]/60"
      />
    </div>
  );
}

function sourceLabel(source: string) {
  return source.replaceAll('_', ' ');
}

function defaultOptions(product: CommerceProduct) {
  if (! product.requires_customization) {
    return {};
  }

  return {
    size: Array.isArray(product.customization_schema.size) ? product.customization_schema.size[1] : 'M',
    color: Array.isArray(product.customization_schema.color) ? product.customization_schema.color[0] : 'Black',
  };
}

function defaultDesign(product: CommerceProduct) {
  if (! product.requires_customization) {
    return {};
  }

  return {
    dj_handle: 'BlendBeats',
    note: 'Starter customization data. Full editor comes later.',
  };
}

export default function MerchPage() {
  const [products, setProducts] = useState<CommerceProduct[]>([]);
  const [cart, setCart] = useState<CommerceCart | null>(null);
  const [loading, setLoading] = useState(true);
  const [busyProduct, setBusyProduct] = useState<number | null>(null);
  const [busyItem, setBusyItem] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' });

    Promise.all([fetchCommerceProducts(), fetchCommerceCart()])
      .then(([productData, cartData]) => {
        setProducts(productData);
        setCart(cartData);
      })
      .catch(() => setError('Commerce data could not be loaded.'))
      .finally(() => setLoading(false));
  }, []);

  const stats = useMemo(() => {
    const external = products.filter((product) => product.external_checkout_required).length;
    const custom = products.filter((product) => product.requires_customization).length;
    const sourceTypes = new Set(products.map((product) => product.source_type)).size;

    return [
      { label: 'Products', value: products.length },
      { label: 'Source types', value: sourceTypes },
      { label: 'External routes', value: external },
      { label: 'Customizable', value: custom },
    ];
  }, [products]);

  async function addProduct(product: CommerceProduct) {
    setBusyProduct(product.id);
    setError(null);

    try {
      const updatedCart = await addCommerceCartItem({
        product_id: product.id,
        quantity: 1,
        selected_options: defaultOptions(product),
        custom_design_data: defaultDesign(product),
      });
      setCart(updatedCart);
    } catch (cartError) {
      setError(cartError instanceof Error ? cartError.message : 'Product could not be added.');
    } finally {
      setBusyProduct(null);
    }
  }

  async function updateQuantity(itemId: number, quantity: number) {
    setBusyItem(itemId);
    setError(null);

    try {
      if (quantity <= 0) {
        setCart(await removeCommerceCartItem(itemId));
      } else {
        setCart(await updateCommerceCartItem(itemId, quantity));
      }
    } catch (cartError) {
      setError(cartError instanceof Error ? cartError.message : 'Cart could not be updated.');
    } finally {
      setBusyItem(null);
    }
  }

  return (
    <>
      <HeaderTitle title="Merch | BlendBeats" description="Hybrid merch cart for internal, affiliate, vendor, and print-on-demand products." />

      <main className="bg-[#080808] text-white">
        <section className="border-b border-[#242424]">
          <div className="container mx-auto grid min-h-[48vh] grid-cols-1 gap-10 px-4 py-16 lg:grid-cols-[1fr_0.85fr] lg:items-end">
            <div>
              <p className="mb-3 text-xs font-bold uppercase tracking-widest text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>
                Commerce Router
              </p>
              <h1 className="max-w-4xl uppercase leading-none" style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3rem, 8vw, 6.25rem)' }}>
                Merch
              </h1>
              <p className="mt-6 max-w-2xl text-lg leading-8 text-[#c9c9c9]">
                Products can route to BlendBeats checkout, affiliate partners, print-on-demand fulfillment, or vendor checkout without forcing one payment path.
              </p>
            </div>

            <div className="grid grid-cols-2 border border-[#2a2a2a] bg-[#111111]">
              {stats.map((stat) => (
                <div key={stat.label} className="border-b border-r border-[#2a2a2a] p-5">
                  <p className="text-4xl text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>
                    {stat.value}
                  </p>
                  <p className="mt-2 text-xs uppercase tracking-widest text-[#888888]" style={{ fontFamily: 'var(--font-heading)' }}>
                    {stat.label}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="py-12">
          <div className="container mx-auto grid grid-cols-1 gap-6 px-4 xl:grid-cols-[1fr_420px]">
            <div>
              <div className="mb-5 flex items-center gap-3">
                <ShoppingBag className="text-primary" size={22} />
                <h2 className="uppercase" style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(1.8rem, 3vw, 2.5rem)' }}>
                  Product Lanes
                </h2>
              </div>

              {error && <div className="mb-5 border border-primary bg-primary/10 p-4 text-sm text-white">{error}</div>}

              {loading ? (
                <div className="flex min-h-72 items-center justify-center border border-[#292929] bg-[#101010]">
                  <Loader2 className="animate-spin text-primary" size={28} />
                </div>
              ) : (
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                  {products.map((product) => (
                    <article key={product.id} className="border border-[#292929] bg-[#121212]">
                      <div className="relative min-h-56 overflow-hidden border-b border-[#292929]">
                        <ProductArtwork product={product} />
                        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent" />
                      </div>
                      <div className="p-5">
                        <div className="mb-3 flex items-start justify-between gap-4">
                          <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>
                              {sourceLabel(product.source_type)}
                            </p>
                            <h3 className="mt-2 text-2xl uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
                              {product.title}
                            </h3>
                          </div>
                          <p className="text-2xl text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>
                            {product.price_label}
                          </p>
                        </div>
                        <p className="min-h-16 text-sm leading-6 text-[#a9a9a9]">{product.description}</p>
                        <div className="mt-5 grid grid-cols-2 gap-2 text-xs uppercase tracking-widest text-[#8d8d8d]" style={{ fontFamily: 'var(--font-heading)' }}>
                          <span className="border border-[#292929] p-3">{product.fulfillment_type.replaceAll('_', ' ')}</span>
                          <span className="border border-[#292929] p-3">{product.vendor_name || 'BlendBeats'}</span>
                        </div>
                        <button
                          type="button"
                          onClick={() => addProduct(product)}
                          disabled={busyProduct === product.id}
                          className="mt-5 inline-flex w-full items-center justify-center gap-2 bg-primary px-5 py-4 text-xs font-bold uppercase tracking-widest text-white disabled:opacity-60"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          {busyProduct === product.id ? <Loader2 className="animate-spin" size={15} /> : <Plus size={15} />}
                          Add To Routed Cart
                        </button>
                      </div>
                    </article>
                  ))}
                </div>
              )}
            </div>

            <aside className="border border-[#292929] bg-[#101010] p-5 xl:sticky xl:top-28 xl:self-start">
              <div className="mb-5 flex items-center justify-between gap-4">
                <div>
                  <p className="text-xs uppercase tracking-widest text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>
                    Routing Cart
                  </p>
                  <h2 className="text-3xl uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
                    {cart?.estimated_total_label || '$0.00'}
                  </h2>
                </div>
                <Route className="text-primary" size={28} />
              </div>

              {!cart || cart.items.length === 0 ? (
                <div className="border border-[#292929] p-5 text-sm leading-6 text-[#a9a9a9]">
                  Add products to see how checkout splits between BlendBeats, partner redirects, vendor checkout, and print-on-demand routes.
                </div>
              ) : (
                <div className="space-y-5">
                  {Object.entries(cart.checkout_groups).map(([key, group]) => (
                    <section key={key} className="border border-[#292929]">
                      <div className="border-b border-[#292929] p-4">
                        <p className="text-lg uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
                          {group.label}
                        </p>
                        <p className="mt-1 text-xs uppercase tracking-widest text-[#888888]">{group.item_count} items / {group.total_label}</p>
                      </div>
                      <div className="divide-y divide-[#292929]">
                        {group.items.map((item) => (
                          <div key={item.id} className="p-4">
                            <div className="flex items-start justify-between gap-3">
                              <div>
                                <p className="font-bold">{item.title}</p>
                                <p className="mt-1 text-xs uppercase tracking-widest text-[#888888]">{item.vendor_name || 'BlendBeats'}</p>
                              </div>
                              <p className="text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>{item.estimated_total_label}</p>
                            </div>
                            <div className="mt-4 flex items-center justify-between gap-3">
                              <div className="flex items-center border border-[#333333]">
                                <button className="p-2" type="button" onClick={() => updateQuantity(item.id, item.quantity - 1)} disabled={busyItem === item.id}>
                                  <Minus size={14} />
                                </button>
                                <span className="min-w-10 text-center text-sm">{item.quantity}</span>
                                <button className="p-2" type="button" onClick={() => updateQuantity(item.id, item.quantity + 1)} disabled={busyItem === item.id}>
                                  <Plus size={14} />
                                </button>
                              </div>
                              <div className="flex items-center gap-2">
                                {item.external_checkout_required && item.affiliate_tracking_url && (
                                  <a className="inline-flex items-center gap-2 border border-[#333333] px-3 py-2 text-xs uppercase tracking-widest" href={item.affiliate_tracking_url} target="_blank" rel="noreferrer">
                                    Partner <ExternalLink size={13} />
                                  </a>
                                )}
                                <button className="inline-flex items-center gap-2 border border-[#333333] px-3 py-2 text-xs uppercase tracking-widest" type="button" onClick={() => updateQuantity(item.id, 0)} disabled={busyItem === item.id}>
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
            </aside>
          </div>
        </section>
      </main>
    </>
  );
}

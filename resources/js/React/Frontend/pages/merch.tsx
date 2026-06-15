import { useEffect, useMemo, useState } from 'react';
import { Loader2, Plus, ShoppingBag } from 'lucide-react';

import HeaderTitle from '@/layouts/HeaderTitle';
import {
  addCommerceCartItem,
  CommerceProduct,
  fetchCommerceProducts,
} from '@/lib/commerce';

function ProductArtwork({ product }: { product: CommerceProduct }) {
  const [imageFailed, setImageFailed] = useState(false);
  const imageUrl = typeof product.image_url === 'string' && product.image_url.trim() !== '' ? product.image_url : null;

  if (imageUrl && !imageFailed) {
    return (
      <img
        src={imageUrl}
        alt={`${product.title} product image`}
        loading="lazy"
        onError={() => setImageFailed(true)}
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
  const [loading, setLoading] = useState(true);
  const [busyProduct, setBusyProduct] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' });

    fetchCommerceProducts()
      .then((productData) => {
        setProducts(productData);
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
      await addCommerceCartItem({
        product_id: product.id,
        quantity: 1,
        selected_options: defaultOptions(product),
        custom_design_data: defaultDesign(product),
      });
    } catch (cartError) {
      setError(cartError instanceof Error ? cartError.message : 'Product could not be added.');
    } finally {
      setBusyProduct(null);
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
          <div className="container mx-auto px-4">
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

          </div>
        </section>
      </main>
    </>
  );
}

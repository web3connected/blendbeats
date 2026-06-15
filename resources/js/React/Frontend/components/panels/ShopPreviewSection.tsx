import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { ArrowRight, Loader2, Plus, ShoppingBag } from 'lucide-react';
import { useEffect, useState } from 'react';

import { fadeUp } from '@/config/animations';
import {
  addCommerceCartItem,
  CommerceProduct,
  fetchCommerceProducts,
} from '@/lib/commerce';

function sourceLabel(source: string) {
  return source.replaceAll('_', ' ');
}

function productOptions(product: CommerceProduct) {
  if (!product.requires_customization) return {};

  return {
    size: Array.isArray(product.customization_schema.size) ? product.customization_schema.size[1] : 'M',
    color: Array.isArray(product.customization_schema.color) ? product.customization_schema.color[0] : 'Black',
  };
}

function productDesign(product: CommerceProduct) {
  if (!product.requires_customization) return {};

  return {
    dj_handle: 'BlendBeats',
    note: 'Starter customization data. Full editor comes later.',
  };
}

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
        className="h-full w-full object-cover opacity-70 transition-all duration-500 group-hover:scale-105 group-hover:opacity-90"
      />
    );
  }

  return (
    <div className="h-full w-full bg-[radial-gradient(circle_at_55%_30%,rgba(255,184,0,0.24),transparent_34%),linear-gradient(135deg,#1b1b1b,#070707)]">
      <ShoppingBag
        size={64}
        className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-[#FFB800]/60"
      />
    </div>
  );
}

const ShopPreviewSection = () => {
  const [products, setProducts] = useState<CommerceProduct[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [busyProduct, setBusyProduct] = useState<number | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchCommerceProducts({ featured: true, limit: 3 })
      .then(setProducts)
      .catch(() => setError('Featured products could not be loaded.'))
      .finally(() => setIsLoading(false));
  }, []);

  async function addProduct(product: CommerceProduct) {
    setBusyProduct(product.id);
    setError('');

    try {
      await addCommerceCartItem({
        product_id: product.id,
        quantity: 1,
        selected_options: productOptions(product),
        custom_design_data: productDesign(product),
      });
    } catch (cartError) {
      setError(cartError instanceof Error ? cartError.message : 'Product could not be added.');
    } finally {
      setBusyProduct(null);
    }
  }

  return (
    <section className="border-t border-[#1a1a1a] bg-[#0a0a0a] py-20">
      <div className="container mx-auto px-4">
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.4 }}
          className="mb-12 grid gap-5 lg:grid-cols-[1fr_auto] lg:items-end"
        >
          <div>
            <p className="mb-2 text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
              Featured Products
            </p>
            <h2
              className="uppercase text-white"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(2.5rem, 7vw, 6rem)', letterSpacing: '-0.01em', lineHeight: 1 }}
            >
              Shop The Culture
            </h2>
          </div>
          <Link
            to="/merch"
            className="inline-flex h-12 items-center justify-center gap-2 border border-[#333333] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            View All Products <ArrowRight size={15} />
          </Link>
        </motion.div>

        {error && <div className="mb-5 border border-primary bg-primary/10 p-4 text-sm text-white">{error}</div>}

        {isLoading ? (
          <div className="flex min-h-72 items-center justify-center border border-[#292929] bg-[#101010]">
            <Loader2 className="animate-spin text-primary" size={28} />
          </div>
        ) : products.length === 0 ? (
          <div className="border border-[#292929] bg-[#101010] p-8 text-center">
            <ShoppingBag className="mx-auto text-[#ffb800]" size={34} />
            <p className="mt-4 text-sm text-[#999999]">No featured products are available yet.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            {products.map((product, index) => (
              <motion.article
                key={product.id}
                custom={index}
                initial="hidden"
                whileInView="visible"
                viewport={{ once: true }}
                variants={fadeUp}
                className="group overflow-hidden border border-[#2a2a2a] bg-[#121212]"
              >
                <div className="relative aspect-[4/3] overflow-hidden border-b border-[#2a2a2a] bg-[#141414]">
                  <ProductArtwork product={product} />
                  <div className="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/35 to-transparent" />
                  <div className="absolute left-5 top-5 border border-[#3a3a3a] bg-black/70 px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>
                    {sourceLabel(product.source_type)}
                  </div>
                  <div className="absolute bottom-5 right-5 text-3xl text-[#ffb800]" style={{ fontFamily: 'var(--font-heading)' }}>
                    {product.price_label}
                  </div>
                </div>
                <div className="p-6">
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#888888]" style={{ fontFamily: 'var(--font-heading)' }}>
                    {product.vendor_name || 'BlendBeats'} / {product.fulfillment_type.replaceAll('_', ' ')}
                  </p>
                  <h3 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '-0.01em' }}>
                    {product.title}
                  </h3>
                  <p className="mt-3 min-h-12 text-sm leading-6 text-[#a9a9a9]">{product.description}</p>
                  <div className="mt-5 grid gap-2 sm:grid-cols-[1fr_auto]">
                    <button
                      type="button"
                      onClick={() => addProduct(product)}
                      disabled={busyProduct === product.id}
                      className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:opacity-60"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      {busyProduct === product.id ? <Loader2 className="animate-spin" size={15} /> : <Plus size={15} />}
                      Add To Cart
                    </button>
                    <Link
                      to="/merch"
                      className="inline-flex h-12 items-center justify-center border border-[#333333] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-[#ffb800] hover:text-[#ffb800]"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      Details
                    </Link>
                  </div>
                </div>
              </motion.article>
            ))}
          </div>
        )}
      </div>
    </section>
  );
};

export default ShopPreviewSection;

import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { ShoppingBag } from 'lucide-react';
import { fadeUp } from '@/config/animations';

const ShopPreviewSection = () => {
  return (
    <section className="py-20 bg-[#0a0a0a] border-t border-[#1a1a1a]">
        <div className="container mx-auto px-4">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.4 }}
            className="text-center mb-12"
          >
            <p className="text-primary text-xs font-bold tracking-widest uppercase mb-2" style={{ fontFamily: 'var(--font-heading)' }}>
              GEAR UP
            </p>
            <h2
              className="text-white uppercase"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(2.5rem, 7vw, 6rem)', letterSpacing: '-0.01em', lineHeight: 1 }}
            >
              SHOP THE CULTURE
            </h2>
          </motion.div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Merch */}
            <motion.div
              custom={0}
              initial="hidden"
              whileInView="visible"
              viewport={{ once: true }}
              variants={fadeUp}
              className="relative overflow-hidden group"
            >
              <div className="aspect-[4/3] bg-[#141414] border border-[#2a2a2a] overflow-hidden">
                <img
                  src="/airo-assets/images/pages/home/merch-hoodie"
                  alt="Blend Battlegrounds Merch"
                  className="w-full h-full object-cover opacity-60 group-hover:opacity-80 group-hover:scale-105 transition-all duration-500"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/30 to-transparent" />
              </div>
              <div className="absolute bottom-0 left-0 right-0 p-6">
                <p className="text-[#888888] text-xs font-bold tracking-widest uppercase mb-1" style={{ fontFamily: 'var(--font-heading)' }}>
                  REPRESENT THE CULTURE
                </p>
                <h3 className="text-white text-3xl uppercase mb-4" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '-0.01em' }}>
                  MERCH DROP
                </h3>
                <Link
                  to="/merch"
                  className="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white text-xs font-bold tracking-widest uppercase hover:bg-primary/90 transition-colors"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <ShoppingBag size={14} />
                  SHOP MERCH
                </Link>
              </div>
            </motion.div>

            {/* Gear */}
            <motion.div
              custom={1}
              initial="hidden"
              whileInView="visible"
              viewport={{ once: true }}
              variants={fadeUp}
              className="relative overflow-hidden group"
            >
              <div className="aspect-[4/3] bg-[#141414] border border-[#2a2a2a] overflow-hidden">
                <img
                  src="/airo-assets/images/pages/home/dj-mixer"
                  alt="DJ Gear"
                  className="w-full h-full object-cover opacity-60 group-hover:opacity-80 group-hover:scale-105 transition-all duration-500"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/30 to-transparent" />
              </div>
              <div className="absolute bottom-0 left-0 right-0 p-6">
                <p className="text-[#888888] text-xs font-bold tracking-widest uppercase mb-1" style={{ fontFamily: 'var(--font-heading)' }}>
                  TOOLS OF THE TRADE
                </p>
                <h3 className="text-white text-3xl uppercase mb-4" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '-0.01em' }}>
                  DJ GEAR
                </h3>
                <Link
                  to="/gear"
                  className="inline-flex items-center gap-2 px-6 py-3 bg-[#FFB800] text-[#0a0a0a] text-xs font-bold tracking-widest uppercase hover:bg-[#FFB800]/90 transition-colors"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <ShoppingBag size={14} />
                  SHOP GEAR
                </Link>
              </div>
            </motion.div>
          </div>
        </div>
      </section>
  )
}

export default ShopPreviewSection

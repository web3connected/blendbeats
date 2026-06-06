import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { Zap } from 'lucide-react';

const CTASection = () => {
  return (
     <section className="relative py-28 overflow-hidden bg-[#0a0a0a]">
        {/* Diagonal red wash */}
        <div
          className="absolute inset-0"
          style={{
            background: 'linear-gradient(135deg, #FF1A1A 0%, #cc0000 40%, #0a0a0a 70%)',
            clipPath: 'polygon(0 0, 70% 0, 45% 100%, 0 100%)',
          }}
        />
        <div className="absolute inset-0 bg-[#0a0a0a]/60" />

        {/* Background crowd image */}
        <div className="absolute inset-0">
          <img
            src="/airo-assets/images/pages/home/crowd-energy"
            alt=""
            className="w-full h-full object-cover opacity-15"
          />
        </div>

        <div className="relative z-10 container mx-auto px-4 text-center">
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            whileInView={{ opacity: 1, scale: 1 }}
            viewport={{ once: true }}
            transition={{ duration: 0.4 }}
          >
            <h2
              className="text-white uppercase leading-none mb-6"
              style={{
                fontFamily: 'var(--font-heading)',
                fontSize: 'clamp(2.5rem, 9vw, 8rem)',
                letterSpacing: '-0.02em',
                textShadow: '0 0 80px rgba(255,26,26,0.4)',
              }}
            >
              THINK YOU GOT<br />WHAT IT TAKES?
            </h2>
            <p className="text-[#cccccc] text-lg mb-10 max-w-xl mx-auto">
              Join thousands of DJs already battling, posting mixes, and building their reputation on the Battlegrounds.
            </p>
            <Link
              to="/battles"
              className="inline-flex items-center gap-3 px-10 py-5 bg-white text-[#0a0a0a] font-black tracking-widest uppercase text-base hover:bg-[#FFB800] transition-colors"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <Zap size={18} />
              JOIN THE BATTLEGROUNDS
            </Link>
          </motion.div>
        </div>
      </section>
  )
}

export default CTASection

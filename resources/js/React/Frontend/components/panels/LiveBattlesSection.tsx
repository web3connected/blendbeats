import { motion } from 'motion/react';
import { fadeUp } from '@/config/animations';
import { homeImageFeatures } from '@/config/home';
import { legacySiteMedia } from '@/lib/site-media';

const LiveBattlesSection = () => {
  return (
    <section className="bg-[#0a0a0a] border-t border-[#1a1a1a] py-14 lg:py-20">
        <div className="container mx-auto px-4 lg:px-8">
          <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {homeImageFeatures.map((feature, i) => (
              <motion.article
                key={feature.title}
                custom={i}
                initial="hidden"
                whileInView="visible"
                viewport={{ once: true }}
                variants={fadeUp}
                className="group relative min-h-[280px] overflow-hidden border border-[#2a2a2a] bg-[#111111] lg:min-h-[420px]"
              >
                {legacySiteMedia(feature.image) ? (
                  <img
                    src={legacySiteMedia(feature.image)}
                    alt={feature.alt}
                    loading="lazy"
                    className="absolute inset-0 h-full w-full object-cover opacity-65 transition duration-500 group-hover:scale-105 group-hover:opacity-85"
                  />
                ) : (
                  <div className="absolute inset-0 bg-[radial-gradient(circle_at_45%_32%,rgba(255,26,26,0.18),transparent_30%),linear-gradient(135deg,#171717,#070707)] transition duration-500 group-hover:scale-105" />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/45 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 p-5 sm:p-6">
                  <p
                    className="mb-1 text-xs font-bold uppercase tracking-[0.22em] text-primary"
                    style={{ fontFamily: 'var(--font-heading)' }}
                  >
                    {feature.label}
                  </p>
                  <h2
                    className="text-4xl uppercase leading-none text-white sm:text-5xl"
                    style={{ fontFamily: 'var(--font-heading)' }}
                  >
                    {feature.title}
                  </h2>
                </div>
              </motion.article>
            ))}
          </div>
        </div>
      </section>
  )
}

export default LiveBattlesSection

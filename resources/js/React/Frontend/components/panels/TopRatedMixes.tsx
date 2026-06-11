import { motion } from "motion/react";
import { Link } from "react-router-dom";
import { ChevronRight, Play } from "lucide-react";
import StarRatingContainer from "../StarRatingContainer";
import WaveFormSVG from "../WaveFormSVG";
import { fadeUp } from "@/config/animations";
import type { HomeMix } from "@/config/home";

const TopRatedMixes = ({
  mixes,
}: {
  mixes: HomeMix[];
}) => {
  // ─── Mix Card ────────────────────────────────────────────────────────────────
  function MixCard({
    djName,
    title,
    genre,
    rating,
    plays,
    delay,
  }: {
    djName: string;
    title: string;
    genre: string;
    rating: number;
    plays: string;
    delay: number;
  }) {
    // ─── Waveform SVG ───────────────────────────────────────────────────────────
    function Waveform() {
      const bars = [
        3, 8, 5, 12, 7, 15, 9, 6, 14, 10, 4, 11, 8, 13, 6, 9, 12, 5, 10, 7,
      ];
      return <WaveFormSVG bars={bars} />;
    }

    return (
      <motion.div
        custom={delay}
        initial="hidden"
        whileInView="visible"
        viewport={{ once: true }}
        variants={fadeUp}
        whileHover={{ y: -4, boxShadow: "0 0 20px rgba(255,26,26,0.2)" }}
        className="bg-[#141414] border border-[#2a2a2a] hover:border-primary/50 transition-all duration-200 p-4 min-w-[220px] flex flex-col gap-3"
      >
        <div className="bg-[#0a0a0a] border border-[#1f1f1f] p-3 flex items-end justify-between">
          <Waveform />
          <button className="w-8 h-8 rounded-full bg-primary flex items-center justify-center hover:bg-primary/80 transition-colors shrink-0 ml-2">
            <Play size={12} className="text-white fill-white ml-0.5" />
          </button>
        </div>
        <div>
          <p
            className="text-white text-sm font-bold leading-tight"
            style={{
              fontFamily: "var(--font-heading)",
              letterSpacing: "0.03em",
            }}
          >
            {title}
          </p>
          <p className="text-[#888888] text-xs mt-0.5">{djName}</p>
        </div>
        <div className="flex items-center justify-between">
          <StarRating rating={rating} animated />
          <span className="text-[10px] text-[#555555]">{plays} plays</span>
        </div>
        <span
          className="inline-block text-[10px] font-bold tracking-widest text-[#FFB800] uppercase border border-[#FFB800]/30 px-2 py-0.5 w-fit"
          style={{ fontFamily: "var(--font-heading)" }}
        >
          {genre}
        </span>
      </motion.div>
    );
  }

  // ─── Star Rating ────────────────────────────────────────────────────────────
  function StarRating({
    rating,
    animated = false,
  }: {
    rating: number;
    animated?: boolean;
  }) {
    return <StarRatingContainer rating={rating} animated={animated} />;
  }

  return (
    <section className="py-20 bg-[#0d0d0d] border-t border-[#1a1a1a]">
      <div className="container mx-auto px-4">
        <div className="flex items-end justify-between mb-10">
          <div>
            <motion.p
              initial={{ opacity: 0 }}
              whileInView={{ opacity: 1 }}
              viewport={{ once: true }}
              className="text-[#FFB800] text-xs font-bold tracking-widest uppercase mb-2"
              style={{ fontFamily: "var(--font-heading)" }}
            >
              ★ COMMUNITY RATED
            </motion.p>
            <motion.h2
              initial={{ opacity: 0, x: -30 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.4 }}
              className="text-white uppercase"
              style={{
                fontFamily: "var(--font-heading)",
                fontSize: "clamp(2.5rem, 6vw, 5rem)",
                letterSpacing: "-0.01em",
                lineHeight: 1,
              }}
            >
              TOP MIXES
            </motion.h2>
          </div>
          <Link
            to="/mixes"
            className="hidden md:flex items-center gap-2 text-[#FFB800] text-xs font-bold tracking-widest uppercase hover:gap-3 transition-all"
            style={{ fontFamily: "var(--font-heading)" }}
          >
            ALL MIXES <ChevronRight size={14} />
          </Link>
        </div>

        {/* Horizontal scroll */}
        <div
          className="flex gap-4 overflow-x-auto pb-4 scrollbar-hide"
          style={{ scrollbarWidth: "none" }}
        >
          {mixes.map((m, i) => (
            <MixCard key={i} {...m} delay={i} />
          ))}
        </div>
      </div>
    </section>
  );
};

export default TopRatedMixes;

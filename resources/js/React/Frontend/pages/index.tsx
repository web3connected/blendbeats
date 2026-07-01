import DJHero from '@/components/panels/DJHero';
import HeaderTitle from '@/layouts/HeaderTitle';
import LiveBattlesSection from '@/components/panels/LiveBattlesSection';
import TopRatedMixes from '@/components/panels/TopRatedMixes';
import HappeningNowSection from '@/components/panels/HappeningNowSection';
import ShopPreviewSection from '@/components/panels/ShopPreviewSection';
import CTASection from '@/components/panels/CTASection';
import TopDJsLeaderboardTeaser from '@/components/panels/TopDJsLeaderboardTeaser';
import { homeSeo } from '@/config/home';


// ─── Main Page ────────────────────────────────────────────────────────────────
export default function HomePage() {
  return (
    <>
      <HeaderTitle title={homeSeo.title} description={homeSeo.description} />

      {/* ── HERO ─────────────────────────────────────────────────────────── */}
      <DJHero />

      {/* ── LIVE BATTLES ─────────────────────────────────────────────────── */}
      <LiveBattlesSection />

      {/* ── HAPPENING NOW ───────────────────────────────────────────────── */}
      <HappeningNowSection />

      {/* ── TOP RATED MIXES ───────────────────────────────────────────────── */}
      <TopRatedMixes />

      {/* ── SHOP THE CULTURE ─────────────────────────────────────────────── */}
      <ShopPreviewSection />

      {/* ── TOP DJS LEADERBOARD TEASER ────────────────────────────────────── */}
      <TopDJsLeaderboardTeaser />

      {/* ── CTA SECTION ──────────────────────────────────────────────────── */}
      <CTASection />
    </>
  );
}

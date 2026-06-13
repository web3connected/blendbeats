import { Pause, Play, Star, Users } from 'lucide-react';
import { useMemo } from 'react';
import { Link } from 'react-router-dom';

import { usePlayer } from '@/components/player/PlayerProvider';
import { useFeaturedDjs, type FeaturedDjSlot } from '@/hooks/use-featured-djs';

const groupLabels: Record<number, string> = {
  1: 'Group A',
  2: 'Group B',
};

function pickSpotlight(slots: FeaturedDjSlot[]) {
  if (slots.length === 0) return null;

  return slots[Math.floor(Math.random() * slots.length)];
}

export default function FeaturedDjSidebarSpotlight() {
  const { slots, isLoading } = useFeaturedDjs();
  const { currentTrack, isPlaying, playTrack, togglePlay } = usePlayer();
  const activePremiumSlots = useMemo(
    () => slots.filter((slot) => slot.dj && (slot.group === 1 || slot.group === 2)),
    [slots],
  );
  const spotlightSlot = useMemo(() => pickSpotlight(activePremiumSlots), [activePremiumSlots]);
  const dj = spotlightSlot?.dj;
  const trackId = dj?.featured_mix ? `dj-hub-sidebar-featured-${dj.featured_mix.id}` : null;
  const isActiveTrack = Boolean(trackId && currentTrack?.id === trackId && isPlaying);

  if (isLoading || !spotlightSlot || !dj) {
    return null;
  }

  return (
    <section className="overflow-hidden border border-primary/40 bg-[#111111]">
      <div className="relative border-b border-[#2a2a2a] bg-[#080808] px-5 pb-5 pt-6">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_20%,rgba(255,29,29,0.22),transparent_48%)]" />
        <div className="relative flex items-start justify-between gap-4">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-widest text-primary">
              Featured DJ
            </p>
            <p className="mt-2 text-xs font-bold uppercase tracking-widest text-[#777777]">
              {groupLabels[spotlightSlot.group] ?? 'Featured'} / Slot {spotlightSlot.number}
            </p>
          </div>
          <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-white">
            <Star size={15} fill="currentColor" />
          </span>
        </div>

        <div className="relative mx-auto mt-5 flex h-32 w-32 items-center justify-center rounded-full border border-[#333333] bg-[#050505] p-2 shadow-2xl shadow-black/40">
          {dj.avatar_url ? (
            <img
              src={dj.avatar_url}
              alt={dj.dj_name}
              className="h-full w-full rounded-full border-2 border-[#f2f2f2] bg-[#090909] object-cover"
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center rounded-full bg-primary text-5xl font-black uppercase text-white">
              {dj.dj_name.charAt(0)}
            </div>
          )}
        </div>
      </div>

      <div className="grid gap-4 p-5">
        <div>
          <p className="text-[11px] font-bold uppercase tracking-widest text-primary">
            {dj.primary_genre ?? 'Open Format'}
          </p>
          <h2 className="mt-2 text-3xl uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {dj.dj_name}
          </h2>
          <p className="mt-3 line-clamp-3 text-sm leading-6 text-[#aaaaaa]">
            {dj.headline || 'Featured BlendBeats DJ building a public portfolio.'}
          </p>
        </div>

        <div className="flex items-center justify-between border border-[#2a2a2a] bg-[#080808] p-3 text-sm">
          <span className="inline-flex items-center gap-2 text-[#888888]">
            <Users size={15} className="text-primary" />
            Followers
          </span>
          <span className="font-semibold text-white">{dj.followers_count.toLocaleString()}</span>
        </div>

        {dj.featured_mix && trackId && (
          <button
            type="button"
            onClick={() => {
              if (currentTrack?.id === trackId) {
                togglePlay();
                return;
              }

              playTrack({
                id: trackId,
                title: dj.featured_mix!.title,
                artist: dj.dj_name,
                src: dj.featured_mix!.url,
                artwork: dj.avatar_url,
                meta: `${dj.primary_genre ?? 'Open Format'} featured mix`,
              });
            }}
            className="inline-flex h-10 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            {isActiveTrack ? <Pause size={14} /> : <Play size={14} fill="currentColor" />}
            {isActiveTrack ? 'Pause Mix' : 'Play Mix'}
          </button>
        )}

        <Link
          to={`/djs/${dj.handle}`}
          className="inline-flex h-10 items-center justify-center border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          View Profile
        </Link>
      </div>
    </section>
  );
}

import { Star } from 'lucide-react';
import { Link } from 'react-router-dom';

import type { FeaturedDjSlot } from '@/hooks/use-featured-djs';

type FeaturedDjSlotCardProps = {
  slot: FeaturedDjSlot;
  emptyMessage?: string;
};

export default function FeaturedDjSlotCard({ slot, emptyMessage }: FeaturedDjSlotCardProps) {
  const dj = slot.dj;

  return (
    <article className="group overflow-hidden border border-[#222222] bg-[#111111] p-5 transition-colors hover:border-primary">
      <div className="mb-4 flex items-center justify-between gap-4">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">
            Featured Slot {slot.number}
          </p>
          <p className="mt-2 text-sm uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {dj ? 'Sponsored DJ' : 'Available'}
          </p>
        </div>
        {dj?.avatar_url ? (
          <img
            src={dj.avatar_url}
            alt={dj.dj_name}
            className="h-12 w-12 rounded-full border border-[#333333] bg-[#080808] object-cover"
          />
        ) : (
          <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#0b0b0b] text-lg font-black uppercase text-white">
            {dj ? dj.dj_name.charAt(0) : '+'}
          </div>
        )}
      </div>

      {dj ? (
        <div className="grid gap-3">
          <p className="inline-flex items-center gap-2 text-sm text-[#aaaaaa]">
            <Star size={14} className="text-primary" />
            {dj.featured_statuses[0] ?? 'Paid Spotlight'}
          </p>
          <p className="text-xl font-bold uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {dj.dj_name}
          </p>
          <p className="text-sm text-[#888888]">
            {dj.primary_genre ?? 'Open Format'} - {dj.location || 'World'}
          </p>
          <div className="grid gap-2 border border-[#222222] bg-[#080808] p-4">
            <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Followers</p>
            <p className="text-lg font-semibold text-white">{dj.followers_count.toLocaleString()}</p>
          </div>
          <Link
            to={`/djs/${dj.handle}`}
            className="inline-flex h-11 items-center justify-center border border-[#333333] bg-[#0d0d0d] px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            View profile
          </Link>
        </div>
      ) : (
        <div className="grid gap-3">
          <p className="text-sm leading-6 text-[#aaaaaa]">
            {emptyMessage ?? 'This slot is open for DJs who want premium visibility.'}
          </p>
          <div className="border border-[#222222] bg-[#080808] p-4 text-sm text-[#dddddd]">
            <p className="font-semibold uppercase tracking-widest text-primary">Claim now</p>
            <p className="mt-1 text-[#888888]">Contact support to reserve this position.</p>
          </div>
        </div>
      )}
    </article>
  );
}

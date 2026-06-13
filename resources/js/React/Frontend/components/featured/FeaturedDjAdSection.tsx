import FeaturedDjSlotCard from '@/components/featured/FeaturedDjSlotCard';
import { useFeaturedDjs } from '@/hooks/use-featured-djs';

type FeaturedDjAdSectionProps = {
  placement: 'dj-hub' | 'dj-lounge';
};

const placementCopy = {
  'dj-hub': {
    eyebrow: 'Featured DJs',
    title: 'Featured placements',
    description: 'Active DJ campaigns rotate through this spotlight area.',
  },
  'dj-lounge': {
    eyebrow: 'Featured DJ',
    title: 'Lounge spotlight',
    description: 'Active DJ campaigns can appear in the Lounge spotlight.',
  },
};

export default function FeaturedDjAdSection({ placement }: FeaturedDjAdSectionProps) {
  const { selectedGroup, isLoading } = useFeaturedDjs();
  const activeSlots = selectedGroup.filter((slot) => slot.dj);
  const copy = placementCopy[placement];

  if (!isLoading && activeSlots.length === 0) {
    return null;
  }

  if (isLoading) {
    return null;
  }

  return (
    <section className="border-b border-[#1a1a1a] bg-[#090909] px-4 py-10 lg:px-8">
      <div className="container mx-auto max-w-6xl">
        <div className="mb-8">
          <p className="text-[11px] font-bold uppercase tracking-widest text-primary">{copy.eyebrow}</p>
          <h2 className="mt-3 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {copy.title}
          </h2>
          <p className="mt-3 max-w-2xl text-sm leading-6 text-[#aaaaaa]">{copy.description}</p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {activeSlots.map((slot) => (
            <FeaturedDjSlotCard key={slot.number} slot={slot} />
          ))}
        </div>
      </div>
    </section>
  );
}

import AllGroupsDisplay from '@/components/advertising/AllGroupsDisplay';
import GroupAAndBDisplay from '@/components/advertising/GroupAAndBDisplay';
import GroupCAndDDisplay from '@/components/advertising/GroupCAndDDisplay';
import GroupEAndFDisplay from '@/components/advertising/GroupEAndFDisplay';

export default function MixesFeaturedDjAdSpaces() {
  return (
    <section className="border-b border-[#1f1f1f] px-4 py-12 lg:px-8">
      <div className="container mx-auto max-w-6xl">
        <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
          <div>
            <p
              className="text-xs font-bold uppercase tracking-[0.3em] text-[#FFB800]"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Featured DJs
            </p>
            <h2 className="mt-2 text-5xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              Spotlight
            </h2>
          </div>
          <p className="max-w-md text-sm leading-6 text-[#888888]">
            Four campaign display spaces for featured DJs. Featured mix placements can replace these spots later.
          </p>
        </div>

        <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
          <GroupAAndBDisplay />
          <GroupCAndDDisplay />
          <GroupEAndFDisplay />
          <AllGroupsDisplay />
        </div>
      </div>
    </section>
  );
}

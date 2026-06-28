import SectionPage from './_section-page';

export default function BattlesPage() {
  return (
    <SectionPage
      eyebrow="Coming soon"
      title="DJ Battle Arena"
      description="Challenge another DJ, record your set, upload your entry, let the community vote, and see who takes the win."
      ctaLabel="Battles coming soon"
      ctaHref="/battles"
      features={[
        {
          title: 'Challenge DJs',
          meta: 'Head-to-head',
          body: 'DJs will be able to challenge other battle-ready public profiles and start a one-on-one matchup.',
        },
        {
          title: 'Record & Upload',
          meta: 'Timed entries',
          body: 'Each DJ will record a timed battle entry directly in the browser and submit one locked performance.',
        },
        {
          title: 'Community Voting',
          meta: 'Fair scoring',
          body: 'Authenticated listeners will vote across battle categories, with one vote per user per battle.',
        },
      ]}
      stats={[
        { value: '1v1', label: 'DJ battles' },
        { value: '4', label: 'Score categories' },
        { value: '1', label: 'Entry per DJ' },
        { value: 'Soon', label: 'Arena launch' },
      ]}
    />
  );
}

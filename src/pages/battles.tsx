import SectionPage from './_section-page';

export default function BattlesPage() {
  return (
    <SectionPage
      eyebrow="Live arena"
      title="Battles"
      description="Vote on head-to-head DJ matchups, follow live score swings, and track who owns each sound clash."
      ctaLabel="Enter battle"
      ctaHref="/battles"
      features={[
        { title: 'Active Matchups', meta: 'Live', body: 'Featured battles stay visible with vote counts, genre tags, and clear winner momentum.' },
        { title: 'Upcoming Brackets', meta: 'Soon', body: 'Tournament rounds can be staged here as the platform adds scheduled competitions.' },
        { title: 'Battle Rules', meta: 'Fair play', body: 'A dedicated page gives competitors and fans one place to understand how voting works.' },
      ]}
      stats={[
        { value: '3', label: 'Featured battles' },
        { value: '7.9K', label: 'Votes tracked' },
        { value: '5', label: 'Genres' },
        { value: '24/7', label: 'Arena access' },
      ]}
    />
  );
}

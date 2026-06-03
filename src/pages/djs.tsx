import SectionPage from './_section-page';

export default function DjsPage() {
  return (
    <SectionPage
      eyebrow="Leaderboard"
      title="DJS"
      description="Showcase ranked DJs, battle records, ratings, genres, and profile paths from the top-DJs homepage section."
      ctaLabel="View rankings"
      ctaHref="/djs"
      features={[
        { title: 'Top Ranked', meta: '#1-#3', body: 'The leaderboard teaser now opens a real page for standings and featured competitors.' },
        { title: 'Profiles', meta: 'Roster', body: 'Each DJ can later get a profile with mixes, battles, bio, and event history.' },
        { title: 'Reputation', meta: 'Stats', body: 'Wins, ratings, and genre specialties give fans quick context before voting.' },
      ]}
      stats={[
        { value: '3', label: 'Featured DJs' },
        { value: '64', label: 'Combined wins' },
        { value: '4.8', label: 'Avg rating' },
        { value: '5', label: 'Styles' },
      ]}
    />
  );
}

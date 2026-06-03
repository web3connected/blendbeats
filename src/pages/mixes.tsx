import SectionPage from './_section-page';

export default function MixesPage() {
  return (
    <SectionPage
      eyebrow="Listen and rate"
      title="Mixes"
      description="Browse featured DJ mixes, discover genre lanes, and give listeners a real destination from every mix call-to-action."
      ctaLabel="Browse mixes"
      ctaHref="/mixes"
      accent="gold"
      features={[
        { title: 'Featured Sets', meta: 'Curated', body: 'Highlight the strongest mixes from active battleground DJs and rising names.' },
        { title: 'Ratings', meta: 'Community', body: 'Star ratings and play counts create a lightweight reputation layer for every mix.' },
        { title: 'Genre Rows', meta: 'Discovery', body: 'Hip-hop, house, drum and bass, techno, and scratch sets can each earn their own lanes.' },
      ]}
      stats={[
        { value: '5', label: 'Featured mixes' },
        { value: '58K', label: 'Total plays' },
        { value: '4.8', label: 'Avg rating' },
        { value: '5', label: 'Genres' },
      ]}
    />
  );
}

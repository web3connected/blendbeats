import SectionPage from './_section-page';

export default function GearPage() {
  return (
    <SectionPage
      eyebrow="Tools of the trade"
      title="Gear"
      description="Create a proper home for DJ gear recommendations, setup lists, and future affiliate or store links."
      ctaLabel="Explore gear"
      ctaHref="/gear"
      accent="gold"
      features={[
        { title: 'Mixer Picks', meta: 'Core', body: 'A clear route for mixers, controllers, turntables, headphones, and battle-ready hardware.' },
        { title: 'Setup Guides', meta: 'Learn', body: 'Future content can compare starter, club, and battle station setups without changing nav.' },
        { title: 'Trusted Tools', meta: 'Curated', body: 'Gear pages can favor tested equipment and avoid sending users to placeholder screens.' },
      ]}
      stats={[
        { value: '5', label: 'Gear lanes' },
        { value: '3', label: 'Setup tiers' },
        { value: '1', label: 'Real page' },
        { value: '0', label: 'Broken nav' },
      ]}
    />
  );
}

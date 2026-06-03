import SectionPage from './_section-page';

export default function MerchPage() {
  return (
    <SectionPage
      eyebrow="Represent the culture"
      title="Merch"
      description="Give fans a real merch destination for drops, apparel, event capsules, and future checkout integrations."
      ctaLabel="View drops"
      ctaHref="/merch"
      features={[
        { title: 'Hoodies and Tees', meta: 'Drop 01', body: 'The homepage merch card now points to a page that can grow into product listings.' },
        { title: 'Event Capsules', meta: 'Limited', body: 'Battle-specific drops can live here without sending users into a dead route.' },
        { title: 'Fan Kits', meta: 'Coming', body: 'Stickers, posters, and digital assets can be grouped as the brand library expands.' },
      ]}
      stats={[
        { value: '01', label: 'Drop ready' },
        { value: '4', label: 'Product lanes' },
        { value: '0', label: 'Dead links' },
        { value: '100%', label: 'Culture' },
      ]}
    />
  );
}

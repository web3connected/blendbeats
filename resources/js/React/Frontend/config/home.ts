export type HomeImageFeature = {
  title: string;
  label: string;
  image: string;
  alt: string;
};

export const homeSeo = {
  title: 'Blend Battlegrounds - Home',
  description:
    'Join the ultimate DJ community. Compete in live battles, post your mixes, and shop the gear that moves the culture.',
};

export const homeImageFeatures: HomeImageFeature[] = [
  {
    title: 'Two Decks',
    label: 'Battle Setup',
    image: '/airo-assets/images/pages/home/turntables',
    alt: 'DJ turntables ready for a battle set',
  },
  {
    title: 'Sharp Cues',
    label: 'Mix Control',
    image: '/airo-assets/images/pages/home/dj-headphones',
    alt: 'Professional DJ headphones in a dark studio setup',
  },
  {
    title: 'Crowd Proof',
    label: 'Live Energy',
    image: '/airo-assets/images/pages/home/crowd-energy',
    alt: 'A crowd moving under concert lights',
  },
];

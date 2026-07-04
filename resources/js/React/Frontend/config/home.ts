export type HomeImageFeature = {
  title: string;
  label: string;
  image: string;
  alt: string;
};

export const homeSeo = {
  title: 'Blend Battlegrounds - Home',
  description:
    'Where DJs go to war. Join during the July 4 beta launch and get 1,000 coins to test battles, voting, and the wallet system.',
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

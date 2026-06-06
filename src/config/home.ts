export type HomeBattle = {
  dj1: string;
  dj2: string;
  genre: string;
  votes1: number;
  votes2: number;
  live: boolean;
};

export type HomeMix = {
  djName: string;
  title: string;
  genre: string;
  rating: number;
  plays: string;
};

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

export const homeBattles: HomeBattle[] = [
  { dj1: 'DJ KROME', dj2: 'SPINMASTER X', genre: 'Hip-Hop', votes1: 1842, votes2: 1203, live: true },
  { dj1: 'LADY FREQ', dj2: 'BASS PROPHET', genre: 'House', votes1: 987, votes2: 1456, live: true },
  { dj1: 'VINYL KING', dj2: 'DJ STATIC', genre: 'Drum & Bass', votes1: 2310, votes2: 2188, live: false },
];

export const homeMixes: HomeMix[] = [
  { djName: 'DJ KROME', title: 'UNDERGROUND SESSIONS VOL.7', genre: 'Hip-Hop', rating: 5, plays: '12.4K' },
  { djName: 'LADY FREQ', title: 'DEEP HOUSE CHRONICLES', genre: 'House', rating: 4, plays: '8.7K' },
  { djName: 'BASS PROPHET', title: 'BASS HEAVY RITUAL', genre: 'Drum & Bass', rating: 5, plays: '21.1K' },
  { djName: 'VINYL KING', title: 'WAX ON WAX OFF', genre: 'Scratch', rating: 4, plays: '6.3K' },
  { djName: 'DJ STATIC', title: 'FREQUENCY WARS EP.3', genre: 'Techno', rating: 5, plays: '9.8K' },
];

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

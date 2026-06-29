import { Link as LinkIcon, Swords, Music2, UserRound } from 'lucide-react';

export const steps = [
  { label: 'Identity', icon: UserRound },
  { label: 'Sound', icon: Music2 },
  { label: 'Links', icon: LinkIcon },
  { label: 'Battle', icon: Swords },
] as const;

export const djTypes = [
  { value: '', label: 'Choose type' },
  { value: 'battle_dj', label: 'Battle DJ' },
  { value: 'club_dj', label: 'Club DJ' },
  { value: 'radio_dj', label: 'Radio DJ' },
  { value: 'mobile_event_dj', label: 'Mobile/Event DJ' },
  { value: 'producer_dj', label: 'Producer DJ' },
  { value: 'turntablist', label: 'Turntablist' },
  { value: 'open_format', label: 'Open Format' },
];

export const visibilityOptions = [
  { value: 'public', label: 'Public' },
  { value: 'followers', label: 'Followers' },
  { value: 'private', label: 'Private' },
];

export const initialDjProfileForm = {
  djName: '',
  handle: '',
  profileHeadline: '',
  bio: '',
  bannerUrl: '',
  primaryGenre: '',
  secondaryGenres: [],
  djType: '',
  city: '',
  state: '',
  country: '',
  website: '',
  instagram: '',
  tiktok: '',
  youtube: '',
  soundcloud: '',
  mixcloud: '',
  twitch: '',
  spotify: '',
  availableForBookings: false,
  battleEnabled: false,
  bookingEmail: '',
  visibility: 'public',
};

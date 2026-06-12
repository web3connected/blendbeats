export type DjProfileFormState = {
  djName: string;
  handle: string;
  profileHeadline: string;
  bio: string;
  bannerUrl: string;
  primaryGenre: string;
  secondaryGenres: string[];
  djType: string;
  city: string;
  state: string;
  country: string;
  website: string;
  instagram: string;
  tiktok: string;
  youtube: string;
  soundcloud: string;
  mixcloud: string;
  twitch: string;
  spotify: string;
  availableForBookings: boolean;
  bookingEmail: string;
  visibility: string;
};

export type UpdateDjProfileField = <FieldName extends keyof DjProfileFormState>(
  field: FieldName,
  value: DjProfileFormState[FieldName],
) => void;

export type AccountAvatarFormState = {
  mode: 'custom' | 'gravatar';
  useGravatar: boolean;
  avatarUrl: string;
  avatarFile: File | null;
  removeCustomAvatar: boolean;
};

export type UpdateAccountAvatarField = <FieldName extends keyof AccountAvatarFormState>(
  field: FieldName,
  value: AccountAvatarFormState[FieldName],
) => void;

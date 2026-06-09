import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowLeft, ArrowRight, Save } from 'lucide-react';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { saveAccountAvatar } from '@/lib/account';
import { DjProfileApiError, type DjProfileResponse, getDjProfile, saveDjProfile } from '@/lib/dj-profile';

import { BookingStep } from './profile/BookingStep';
import { initialDjProfileForm, steps } from './profile/constants';
import { IdentityStep } from './profile/IdentityStep';
import { LinksStep } from './profile/LinksStep';
import { SetupProgress } from './profile/SetupProgress';
import { SoundStep } from './profile/SoundStep';
import type { AccountAvatarFormState, DjProfileFormState } from './profile/types';

const validationStepByField: Record<string, number> = {
  dj_name: 0,
  handle: 0,
  profile_headline: 0,
  bio: 0,
  banner_url: 0,
  primary_genre: 1,
  secondary_genres: 1,
  dj_type: 1,
  city: 1,
  state: 1,
  country: 1,
  website: 2,
  instagram: 2,
  tiktok: 2,
  youtube: 2,
  soundcloud: 2,
  mixcloud: 2,
  twitch: 2,
  spotify: 2,
  available_for_bookings: 3,
  booking_email: 3,
  visibility: 3,
};

function normalizeHandle(handle: string) {
  return handle
    .trim()
    .replace(/^@+/, '')
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9_-]/g, '');
}

function getClientValidationError(form: DjProfileFormState): { message: string; step: number } | null {
  if (!form.djName.trim()) return { message: 'DJ Name is required before saving your profile.', step: 0 };
  if (!form.handle.trim()) return { message: 'Handle is required before saving your profile.', step: 0 };
  if (!form.bio.trim()) return { message: 'Bio is required before saving your profile.', step: 0 };
  if (!form.primaryGenre.trim()) return { message: 'Primary Genre is required before saving your profile.', step: 1 };
  if (!form.visibility.trim()) return { message: 'Profile Visibility is required before saving your profile.', step: 3 };

  return null;
}

function getApiValidationError(saveError: DjProfileApiError): { message: string; step: number } {
  const [field, messages] = Object.entries(saveError.errors)[0] ?? [];
  const message = messages?.[0] ?? saveError.message;
  const rootField = field?.split('.')[0] ?? '';

  return {
    message,
    step: validationStepByField[rootField] ?? 0,
  };
}

function formFromProfile(profile: DjProfileResponse): DjProfileFormState {
  return {
    djName: profile.dj_name ?? '',
    handle: profile.handle ?? '',
    profileHeadline: profile.profile_headline ?? '',
    bio: profile.bio ?? '',
    bannerUrl: profile.banner_url ?? '',
    primaryGenre: profile.primary_genre ?? '',
    secondaryGenres: profile.secondary_genres ?? [],
    djType: profile.dj_type ?? '',
    city: profile.city ?? '',
    state: profile.state ?? '',
    country: profile.country ?? '',
    website: profile.website ?? '',
    instagram: profile.instagram ?? '',
    tiktok: profile.tiktok ?? '',
    youtube: profile.youtube ?? '',
    soundcloud: profile.soundcloud ?? '',
    mixcloud: profile.mixcloud ?? '',
    twitch: profile.twitch ?? '',
    spotify: profile.spotify ?? '',
    availableForBookings: Boolean(profile.available_for_bookings),
    bookingEmail: profile.booking_email ?? '',
    visibility: profile.visibility ?? 'public',
  };
}

export default function StartDjCareerPage() {
  const { user, isLoading, refreshUser } = useAuth();
  const [currentStep, setCurrentStep] = useState(0);
  const [identityTab, setIdentityTab] = useState<'profile' | 'avatar'>('profile');
  const [isSaved, setIsSaved] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isProfileLoading, setIsProfileLoading] = useState(false);
  const [hasLoadedProfile, setHasLoadedProfile] = useState(false);
  const [hasAvatarChanges, setHasAvatarChanges] = useState(false);
  const [error, setError] = useState('');
  const [form, setForm] = useState<DjProfileFormState>(initialDjProfileForm);
  const [avatarSettings, setAvatarSettings] = useState<AccountAvatarFormState>({
    mode: 'gravatar',
    useGravatar: true,
    avatarUrl: '',
    avatarFile: null,
    removeCustomAvatar: false,
  });

  const progress = useMemo(() => `${currentStep + 1} / ${steps.length}`, [currentStep]);
  const hasDjProfile = Boolean(user?.dj_profile);

  useEffect(() => {
    if (!hasDjProfile || hasLoadedProfile) return;

    setIsProfileLoading(true);
    setError('');

    getDjProfile()
      .then((profile) => {
        setForm(formFromProfile(profile));
        setHasLoadedProfile(true);
      })
      .catch((profileError) => {
        setError(
          profileError instanceof DjProfileApiError
            ? profileError.message
            : 'Unable to load DJ profile right now.',
        );
      })
      .finally(() => setIsProfileLoading(false));
  }, [hasDjProfile, hasLoadedProfile]);

  useEffect(() => {
    if (!user) return;

    const usesGravatar = Boolean(user.is_gravatar ?? user.use_gravatar);

    setAvatarSettings({
      mode: usesGravatar ? 'gravatar' : 'custom',
      useGravatar: usesGravatar,
      avatarUrl: user.avatar ?? '',
      avatarFile: null,
      removeCustomAvatar: false,
    });
    setHasAvatarChanges(false);
  }, [user]);

  if (isLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20">
        <div className="container mx-auto h-48 max-w-4xl animate-pulse bg-[#141414]" />
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  const updateField = <FieldName extends keyof DjProfileFormState>(
    field: FieldName,
    value: DjProfileFormState[FieldName],
  ) => {
    setIsSaved(false);
    setForm((currentForm) => ({ ...currentForm, [field]: value }));
  };
  const updateAvatarField = <FieldName extends keyof AccountAvatarFormState>(
    field: FieldName,
    value: AccountAvatarFormState[FieldName],
  ) => {
    setIsSaved(false);
    setHasAvatarChanges(true);
    setAvatarSettings((currentSettings) => ({ ...currentSettings, [field]: value }));
  };

  const nextStep = () => setCurrentStep((step) => Math.min(step + 1, steps.length - 1));
  const previousStep = () => setCurrentStep((step) => Math.max(step - 1, 0));
  const pageTitle = hasDjProfile ? 'Edit DJ Profile' : 'Start Your DJ Profile';
  const ActiveIcon = steps[currentStep].icon;

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError('');

    if (currentStep < steps.length - 1) {
      nextStep();
      return;
    }

    const normalizedHandle = normalizeHandle(form.handle);
    const formForSave = { ...form, handle: normalizedHandle };
    const clientValidationError = getClientValidationError(formForSave);

    if (form.handle !== normalizedHandle) {
      setForm(formForSave);
    }

    if (clientValidationError) {
      setCurrentStep(clientValidationError.step);
      setIdentityTab('profile');
      setError(clientValidationError.message);
      return;
    }

    setIsSubmitting(true);

    try {
      if (hasAvatarChanges) {
        await saveAccountAvatar({
          useGravatar: avatarSettings.useGravatar,
          avatarUrl: avatarSettings.avatarUrl,
          avatarFile: avatarSettings.avatarFile,
          removeAvatar: avatarSettings.removeCustomAvatar,
        });
      }

      await saveDjProfile({
        dj_name: formForSave.djName.trim(),
        handle: formForSave.handle,
        profile_headline: formForSave.profileHeadline.trim(),
        bio: formForSave.bio.trim(),
        banner_url: formForSave.bannerUrl.trim(),
        primary_genre: formForSave.primaryGenre.trim(),
        secondary_genres: formForSave.secondaryGenres,
        dj_type: formForSave.djType,
        city: formForSave.city.trim(),
        state: formForSave.state.trim(),
        country: formForSave.country.trim(),
        website: formForSave.website.trim(),
        instagram: formForSave.instagram.trim(),
        tiktok: formForSave.tiktok.trim(),
        youtube: formForSave.youtube.trim(),
        soundcloud: formForSave.soundcloud.trim(),
        mixcloud: formForSave.mixcloud.trim(),
        twitch: formForSave.twitch.trim(),
        spotify: formForSave.spotify.trim(),
        available_for_bookings: formForSave.availableForBookings,
        booking_email: formForSave.bookingEmail.trim(),
        visibility: formForSave.visibility,
      });

      await refreshUser();
      setIsSaved(true);
      setHasLoadedProfile(true);
      setHasAvatarChanges(false);
    } catch (saveError) {
      if (saveError instanceof DjProfileApiError) {
        const apiValidationError = getApiValidationError(saveError);
        setCurrentStep(apiValidationError.step);
        setIdentityTab('profile');
        setError(apiValidationError.message);
        return;
      }

      setError('Unable to save DJ profile right now.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>{pageTitle} | The Blend Battlegrounds</title>
        <meta name="description" content="Create your DJ profile on The Blend Battlegrounds." />
      </Helmet>
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p
                  className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  DJ Career
                </p>
                <h1
                  className="text-white uppercase leading-none"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 9vw, 7rem)' }}
                >
                  {pageTitle}
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  {hasDjProfile
                    ? 'Update your public professional DJ profile in a few focused steps.'
                    : 'Build a public professional DJ profile in a few focused steps.'}
                </p>
              </div>
              <Link
                to="/dashboard"
                className="inline-flex h-12 items-center justify-center border border-[#444444] px-5 text-sm font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Back To Dashboard
              </Link>
            </div>
          </div>
        </section>

        <section className="px-4 py-8 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">
            <SetupProgress currentStep={currentStep} progress={progress} onStepChange={setCurrentStep} />

            <form onSubmit={handleSubmit} className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
              <div className="mb-6 flex flex-col gap-3 border-b border-[#242424] pb-5 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                  <ActiveIcon size={20} className="text-primary" />
                  <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {steps[currentStep].label}
                  </h2>
                </div>
                <p className="text-xs font-bold uppercase tracking-widest text-[#777777]">Step {progress}</p>
              </div>

              {isProfileLoading && (
                <div className="border border-[#333333] bg-[#080808] px-4 py-8 text-sm text-[#aaaaaa]">
                  Loading your DJ profile...
                </div>
              )}

              {!isProfileLoading && currentStep === 0 && (
                <IdentityStep
                  form={form}
                  updateField={updateField}
                  identityTab={identityTab}
                  setIdentityTab={setIdentityTab}
                  user={user}
                  avatarSettings={avatarSettings}
                  updateAvatarField={updateAvatarField}
                />
              )}
              {!isProfileLoading && currentStep === 1 && <SoundStep form={form} updateField={updateField} />}
              {!isProfileLoading && currentStep === 2 && <LinksStep form={form} updateField={updateField} />}
              {!isProfileLoading && currentStep === 3 && (
                <BookingStep form={form} updateField={updateField} defaultBookingEmail={user.email} />
              )}

              {isSaved && (
                <div className="mt-5 border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                  DJ profile saved. Dashboard and header actions will now show Go To DJ Profile.
                </div>
              )}

              {error && (
                <div className="mt-5 border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                  {error}
                </div>
              )}

              <div className="mt-8 flex flex-col gap-3 border-t border-[#242424] pt-5 sm:flex-row sm:items-center sm:justify-between">
                <button
                  type="button"
                  onClick={previousStep}
                  disabled={currentStep === 0}
                  className="inline-flex h-11 items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:opacity-40"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <ArrowLeft size={15} />
                  Back
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting || isProfileLoading}
                  className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:opacity-60"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  {currentStep === steps.length - 1 ? (
                    <>
                      <Save size={15} />
                      {isSubmitting ? 'Saving' : 'Save Profile'}
                    </>
                  ) : (
                    <>
                      Continue
                      <ArrowRight size={15} />
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </section>
      </main>
    </>
  );
}

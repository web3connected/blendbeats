import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowLeft, ArrowRight, BadgeCheck, CalendarCheck, LoaderCircle, Music2, Radio, Save, Search, ShieldCheck, Users } from 'lucide-react';
import { type FormEvent, useEffect, useMemo, useState, useRef } from 'react';
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

const gettingStartedBenefits = [
  {
    title: 'Public DJ Profile',
    body: 'Claim your DJ name, handle, headline, bio, location, and sound so fans can find you in the DJ Hub.',
    icon: Radio,
  },
  {
    title: 'Mix And Media Tools',
    body: 'Unlock the creator path for publishing mixes, attaching media, and building a portfolio around your work.',
    icon: Music2,
  },
  {
    title: 'Bookings Ready',
    body: 'Choose whether you are open for bookings and show the right contact email when your profile is public.',
    icon: CalendarCheck,
  },
  {
    title: 'Battle Identity',
    body: 'Use one consistent profile across battles, DJ Lounge posts, mix discovery, and leaderboard moments.',
    icon: BadgeCheck,
  },
];

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

function getStepValidationError(form: DjProfileFormState, step: number): { message: string; step: number } | null {
  if (step === 0) {
    if (!form.djName.trim()) return { message: 'DJ Name is required before saving your identity.', step: 0 };
    if (!form.handle.trim()) return { message: 'Handle is required before saving your identity.', step: 0 };
    if (!form.bio.trim()) return { message: 'Bio is required before saving your identity.', step: 0 };
  }

  if (step === 1 && !form.primaryGenre.trim()) {
    return { message: 'Primary Genre is required before saving your sound.', step: 1 };
  }

  if (step === 3 && !form.visibility.trim()) {
    return { message: 'Profile Visibility is required before saving booking settings.', step: 3 };
  }

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
  const [showGettingStarted, setShowGettingStarted] = useState(true);
  const [hasCreatedDjProfile, setHasCreatedDjProfile] = useState(false);
  const [identityTab, setIdentityTab] = useState<'profile' | 'avatar'>('profile');
  const [isSaved, setIsSaved] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isProfileLoading, setIsProfileLoading] = useState(false);
  const [hasLoadedProfile, setHasLoadedProfile] = useState(false);
  const [hasAvatarChanges, setHasAvatarChanges] = useState(false);
  const [isAvatarSaving, setIsAvatarSaving] = useState(false);
  const [isStepSaving, setIsStepSaving] = useState(false);
  const prevIdentityTabRef = useRef(identityTab);
  const prevStepRef = useRef(currentStep);
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
  const hasDjProfile = Boolean(user?.dj_profile || hasCreatedDjProfile);

  useEffect(() => {
    if (user?.dj_profile) {
      setHasCreatedDjProfile(true);
      setShowGettingStarted(false);
    }
  }, [user?.dj_profile]);

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

    if (!hasDjProfile && !form.djName) {
      setForm((currentForm) => ({
        ...currentForm,
        djName: user.name,
      }));
    }

    const usesGravatar = Boolean(user.is_gravatar ?? user.use_gravatar);

    setAvatarSettings({
      mode: usesGravatar ? 'gravatar' : 'custom',
      useGravatar: usesGravatar,
      avatarUrl: '',
      avatarFile: null,
      removeCustomAvatar: false,
    });
    setHasAvatarChanges(false);
  }, [user, hasDjProfile, form.djName]);

  // Auto-save avatar when leaving the avatar tab or moving away from the identity step
  useEffect(() => {
    const prevIdentity = prevIdentityTabRef.current;
    const prevStep = prevStepRef.current;

    // If we had avatar changes and the user left the avatar tab
    if (hasAvatarChanges && prevIdentity === 'avatar' && identityTab !== 'avatar') {
      void (async () => {
        setIsAvatarSaving(true);
        try {
          await saveAccountAvatar({
            useGravatar: avatarSettings.useGravatar,
            avatarUrl: avatarSettings.avatarUrl,
            avatarFile: avatarSettings.avatarFile,
            removeAvatar: avatarSettings.removeCustomAvatar,
          });
          await refreshUser();
          setHasAvatarChanges(false);
        } catch (err) {
          // preserve error UI handled elsewhere
        } finally {
          setIsAvatarSaving(false);
        }
      })();
    }

    // If we had avatar changes and user navigated away from step 0 (identity)
    if (hasAvatarChanges && prevStep === 0 && currentStep !== 0) {
      void (async () => {
        setIsAvatarSaving(true);
        try {
          await saveAccountAvatar({
            useGravatar: avatarSettings.useGravatar,
            avatarUrl: avatarSettings.avatarUrl,
            avatarFile: avatarSettings.avatarFile,
            removeAvatar: avatarSettings.removeCustomAvatar,
          });
          await refreshUser();
          setHasAvatarChanges(false);
        } catch (err) {
          // ignore here
        } finally {
          setIsAvatarSaving(false);
        }
      })();
    }

    prevIdentityTabRef.current = identityTab;
    prevStepRef.current = currentStep;
  }, [identityTab, currentStep, hasAvatarChanges, avatarSettings, refreshUser]);

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

  const buildProfilePayload = (formForSave: DjProfileFormState, isFinal = false) => ({
    is_final: isFinal,
    setup_step: currentStep,
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

  const saveAvatarChanges = async () => {
    if (!hasAvatarChanges) return;

    await saveAccountAvatar({
      useGravatar: avatarSettings.useGravatar,
      avatarUrl: avatarSettings.avatarUrl,
      avatarFile: avatarSettings.avatarFile,
      removeAvatar: avatarSettings.removeCustomAvatar,
    });
    await refreshUser();
    setHasAvatarChanges(false);
  };

  const saveStepAndMove = async (targetStep: number) => {
    if (targetStep === currentStep || isStepSaving || isSubmitting || isProfileLoading) return;

    const normalizedHandle = normalizeHandle(form.handle);
    const formForSave = { ...form, handle: normalizedHandle };
    const stepValidationError = getStepValidationError(formForSave, currentStep);

    if (form.handle !== normalizedHandle) {
      setForm(formForSave);
    }

    if (stepValidationError) {
      setCurrentStep(stepValidationError.step);
      if (stepValidationError.step === 0) setIdentityTab('profile');
      setError(stepValidationError.message);
      return;
    }

    setError('');
    setIsSaved(false);
    setIsStepSaving(true);

    try {
      await saveAvatarChanges();
      await saveDjProfile(buildProfilePayload(formForSave, false));
      setHasLoadedProfile(true);
      setHasCreatedDjProfile(true);
      setShowGettingStarted(false);
      if (!user?.dj_profile) {
        await refreshUser();
      }
      setCurrentStep(Math.max(0, Math.min(targetStep, steps.length - 1)));
    } catch (saveError) {
      if (saveError instanceof DjProfileApiError) {
        const apiValidationError = getApiValidationError(saveError);
        setCurrentStep(apiValidationError.step);
        if (apiValidationError.step === 0) setIdentityTab('profile');
        setError(apiValidationError.message);
        return;
      }

      setError('Unable to save this step right now.');
    } finally {
      setIsStepSaving(false);
    }
  };

  const nextStep = () => void saveStepAndMove(Math.min(currentStep + 1, steps.length - 1));
  const previousStep = () => void saveStepAndMove(Math.max(currentStep - 1, 0));
  const pageTitle = hasDjProfile ? 'Edit DJ Profile' : 'Start Your DJ Profile';
  const ActiveIcon = steps[currentStep].icon;

  if (!hasDjProfile && showGettingStarted) {
    return (
      <>
        <Helmet>
          <title>Start DJ Career | The Blend Battlegrounds</title>
          <meta name="description" content="Learn what creating a DJ profile unlocks on The Blend Battlegrounds." />
        </Helmet>
        <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] text-white">
          <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
            <div className="container mx-auto max-w-6xl">
              <Link
                to="/dashboard"
                className="mb-8 inline-flex h-10 items-center gap-2 border border-[#333333] px-3 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                <ArrowLeft size={15} />
                Dashboard
              </Link>

              <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
                <div>
                  <p className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                    Getting Started
                  </p>
                  <h1
                    className="max-w-4xl uppercase leading-none"
                    style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.75rem, 10vw, 8rem)' }}
                  >
                    Create Your DJ Account
                  </h1>
                  <p className="mt-6 max-w-2xl text-base leading-7 text-[#b8b8b8] md:text-lg">
                    Your DJ account turns a listener profile into a public creator identity. Set up the essentials once, then use that profile across mixes, battles, DJ Lounge, bookings, and discovery.
                  </p>
                </div>

                <div className="border border-[#2a2a2a] bg-[#111111] p-5">
                  <p className="text-[11px] font-bold uppercase tracking-widest text-[#777777]">Setup Time</p>
                  <p className="mt-2 text-5xl uppercase text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                    4 Steps
                  </p>
                  <p className="mt-3 text-sm leading-6 text-[#aaaaaa]">
                    Identity, sound, links, and booking info. You can come back later and edit everything.
                  </p>
                </div>
              </div>
            </div>
          </section>

          <section className="px-4 py-10 lg:px-8">
            <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
              <div className="grid gap-4 sm:grid-cols-2">
                {gettingStartedBenefits.map(({ title, body, icon: Icon }) => (
                  <article key={title} className="border border-[#2a2a2a] bg-[#111111] p-5">
                    <Icon size={22} className="mb-5 text-primary" />
                    <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      {title}
                    </h2>
                    <p className="mt-3 text-sm leading-6 text-[#aaaaaa]">{body}</p>
                  </article>
                ))}
              </div>

              <aside className="grid gap-5 self-start">
                <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                  <div className="mb-4 flex items-center gap-2">
                    <ShieldCheck size={18} className="text-[#FFB800]" />
                    <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Before You Begin
                    </h2>
                  </div>
                  <div className="grid gap-3 text-sm leading-6 text-[#aaaaaa]">
                    <p>Pick a clean handle fans can remember.</p>
                    <p>Have a short bio ready that explains your sound.</p>
                    <p>Add at least one genre so your profile can appear in discovery filters.</p>
                  </div>
                </section>

                <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                  <div className="mb-4 flex items-center gap-2">
                    <Search size={18} className="text-primary" />
                    <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Visibility
                    </h2>
                  </div>
                  <p className="text-sm leading-6 text-[#aaaaaa]">
                    Set your profile public when you want to show up in DJ Hub. Keep it private while you are still polishing.
                  </p>
                </section>

                <button
                  type="button"
                  onClick={() => setShowGettingStarted(false)}
                  className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Start Setup
                  <ArrowRight size={17} />
                </button>

                <Link
                  to="/djs"
                  className="inline-flex h-12 items-center justify-center gap-2 border border-[#333333] px-5 text-sm font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Users size={17} />
                  View DJ Hub
                </Link>
              </aside>
            </div>
          </section>
        </main>
      </>
    );
  }

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
      await saveAvatarChanges();
      await saveDjProfile(buildProfilePayload(formForSave, true));

      await refreshUser();
      setIsSaved(true);
      setHasLoadedProfile(true);
      setHasCreatedDjProfile(true);
      setShowGettingStarted(false);
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
            <SetupProgress
              currentStep={currentStep}
              progress={progress}
              onStepChange={(step) => void saveStepAndMove(step)}
              isDisabled={isStepSaving || isSubmitting || isProfileLoading}
            />

            <form onSubmit={handleSubmit} className="relative border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
              {isStepSaving && (
                <div className="absolute inset-0 z-20 flex items-center justify-center bg-[#080808]/90 px-5 text-center backdrop-blur-sm">
                  <div className="grid justify-items-center gap-4">
                    <LoaderCircle size={36} className="animate-spin text-primary" />
                    <div>
                      <p className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        Saving Step
                      </p>
                      <p className="mt-2 max-w-sm text-sm leading-6 text-[#aaaaaa]">
                        Hold tight while your DJ profile updates before the next section loads.
                      </p>
                    </div>
                  </div>
                </div>
              )}

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
                  disabled={currentStep === 0 || isStepSaving || isSubmitting}
                  className="inline-flex h-11 items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:opacity-40"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <ArrowLeft size={15} />
                  Back
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting || isProfileLoading || isStepSaving}
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

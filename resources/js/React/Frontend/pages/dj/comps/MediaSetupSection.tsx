import { ArrowRight, LoaderCircle } from 'lucide-react';

type Props = {
    user: {
        dj_profile: {
            dj_name: string;
            handle: string;
        };
    };
    error?: string;
    isActivating?: boolean;
    onActivate?: () => void;
};

const MediaSetupSection = ({
    user,
    error,
    isActivating = false,
    onActivate,
}: Props) => {
  return (
    <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8">
      <div className="container mx-auto max-w-6xl">
        <p
          className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
          style={{ fontFamily: "var(--font-heading)" }}
        >
          Media Setup
        </p>
        <h1
          className="max-w-4xl text-white uppercase leading-none"
          style={{
            fontFamily: "var(--font-heading)",
            fontSize: "clamp(3.75rem, 9vw, 7rem)",
          }}
        >
          Get Started With Your Portfolio
        </h1>
        <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
          Create your free media storage workspace for {user.dj_profile.dj_name}
          . We will use your DJ handle to create a public folder like{" "}
          <span className="text-white">
            media/accounts/{user.dj_profile.handle}
          </span>
          .
        </p>
        {error && (
          <div className="mt-6 max-w-2xl border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
            {error}
          </div>
        )}
        <button
          type="button"
          onClick={onActivate}
          disabled={isActivating}
          className="mt-8 inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:opacity-60"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          {isActivating ? <LoaderCircle size={17} className="animate-spin" /> : <ArrowRight size={17} />}
          {isActivating ? 'Activating Portfolio' : 'Activate Portfolio'}
        </button>
      </div>
    </section>
  );
};

export default MediaSetupSection;

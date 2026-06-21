import { Helmet } from '@dr.pogodin/react-helmet';
import { KeyRound } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';

import AuthCard from '@/components/auth/AuthCard';
import { ApiAuthError, resetPassword } from '@/lib/auth';

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const emailFromUrl = searchParams.get('email') ?? '';
  const [email, setEmail] = useState(emailFromUrl);
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isComplete, setIsComplete] = useState(false);
  const canSubmit = useMemo(
    () => Boolean(token && email && password && passwordConfirmation),
    [email, password, passwordConfirmation, token],
  );

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError('');
    setIsSubmitting(true);

    try {
      await resetPassword(token, email, password, passwordConfirmation);
      setIsComplete(true);
    } catch (submissionError) {
      setError(
        submissionError instanceof ApiAuthError
          ? submissionError.message
          : 'Unable to reset that password right now.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Choose New Password | The Blend Battlegrounds</title>
        <meta name="description" content="Choose a new Blend Battlegrounds password." />
      </Helmet>
      <AuthCard
        eyebrow="Reset access"
        title="Choose New Password"
        subtitle="Enter a new password for your Blend Battlegrounds account."
        footerPrompt="Remembered your password?"
        footerAction="Log in"
        footerHref="/login"
      >
        {isComplete ? (
          <div className="border border-[#2a2a2a] bg-[#080808] p-5">
            <div className="mb-4 inline-flex h-11 w-11 items-center justify-center bg-primary text-white">
              <KeyRound size={20} />
            </div>
            <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              Password Reset
            </h2>
            <p className="mt-3 text-sm leading-6 text-[#aaaaaa]">
              Your password has been updated. Sign in with your new password.
            </p>
            <Link
              to="/login"
              className="mt-5 inline-flex h-11 items-center justify-center bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Log In
            </Link>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-5">
            {!token && (
              <p className="border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                This reset link is missing a token. Request a new password reset email.
              </p>
            )}

            <div>
              <label htmlFor="reset-email" className="text-xs font-bold uppercase tracking-widest text-[#bbbbbb]">
                Email
              </label>
              <input
                id="reset-email"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                required
                className="mt-2 h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
                placeholder="you@example.com"
              />
            </div>

            <div>
              <label htmlFor="new-password" className="text-xs font-bold uppercase tracking-widest text-[#bbbbbb]">
                New Password
              </label>
              <input
                id="new-password"
                type="password"
                autoComplete="new-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
                minLength={8}
                className="mt-2 h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
                placeholder="8+ characters"
              />
            </div>

            <div>
              <label htmlFor="new-password-confirmation" className="text-xs font-bold uppercase tracking-widest text-[#bbbbbb]">
                Confirm Password
              </label>
              <input
                id="new-password-confirmation"
                type="password"
                autoComplete="new-password"
                value={passwordConfirmation}
                onChange={(event) => setPasswordConfirmation(event.target.value)}
                required
                minLength={8}
                className="mt-2 h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
                placeholder="Repeat password"
              />
            </div>

            {error && (
              <p className="border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                {error}
              </p>
            )}

            <button
              type="submit"
              disabled={isSubmitting || !canSubmit}
              className="inline-flex h-12 w-full items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:opacity-60"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <KeyRound size={17} />
              {isSubmitting ? 'Resetting...' : 'Reset Password'}
            </button>
          </form>
        )}
      </AuthCard>
    </>
  );
}

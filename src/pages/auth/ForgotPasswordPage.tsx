import { Helmet } from '@dr.pogodin/react-helmet';
import { Mail } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import AuthCard from '@/components/auth/AuthCard';
import { ApiAuthError, requestPasswordReset } from '@/lib/auth';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError('');
    setIsSubmitting(true);

    try {
      await requestPasswordReset(email);
      setIsSubmitted(true);
    } catch (submissionError) {
      setError(
        submissionError instanceof ApiAuthError
          ? submissionError.message
          : 'Unable to request a reset right now.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Password Reset | The Blend Battlegrounds</title>
        <meta name="description" content="Reset your Blend Battlegrounds password." />
      </Helmet>
      <AuthCard
        eyebrow="Reset access"
        title="Password Reset"
        subtitle="Enter your account email and we will send instructions when password email delivery is enabled."
        footerPrompt="Remembered your password?"
        footerAction="Log in"
        footerHref="/login"
      >
        {isSubmitted ? (
          <div className="border border-[#2a2a2a] bg-[#080808] p-5">
            <div className="mb-4 inline-flex h-11 w-11 items-center justify-center bg-primary text-white">
              <Mail size={20} />
            </div>
            <h2
              className="text-3xl uppercase text-white"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Check Your Email
            </h2>
            <p className="mt-3 text-sm leading-6 text-[#aaaaaa]">
              If an account exists for {email}, password reset instructions will be sent there.
            </p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-5">
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

            {error && (
              <p className="border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                {error}
              </p>
            )}

            <button
              type="submit"
              disabled={isSubmitting}
              className="inline-flex h-12 w-full items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:opacity-60"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <Mail size={17} />
              {isSubmitting ? 'Sending...' : 'Send Reset Link'}
            </button>
          </form>
        )}
      </AuthCard>
    </>
  );
}

import { Helmet } from '@dr.pogodin/react-helmet';
import { Gift, UserPlus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';

import AuthCard from '@/components/auth/AuthCard';
import { useAuth } from '@/components/auth/AuthProvider';
import { ApiAuthError } from '@/lib/auth';

const registerBackground = new URL('../../public/assets/images/register-bg.jpg', import.meta.url).href;

export default function RegisterPage() {
  const navigate = useNavigate();
  const { register } = useAuth();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError('');

    if (password !== passwordConfirmation) {
      setError('Passwords must match.');
      return;
    }

    setIsSubmitting(true);
    try {
      await register(name, email, password, passwordConfirmation);
      navigate('/account');
    } catch (submissionError) {
      setError(
        submissionError instanceof ApiAuthError
          ? submissionError.message
          : 'Unable to create your account right now.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Register | The Blend Battlegrounds</title>
        <meta name="description" content="Create your Blend Battlegrounds account." />
      </Helmet>
      <AuthCard
        eyebrow="Claim your spot"
        title="Register"
        subtitle="Create your profile, join battles, rate mixes, and start building your name in the Blend community. Every new account gets one free 1-day featured ad."
        footerPrompt="Already have an account?"
        footerAction="Log in"
        footerHref="/login"
        backgroundImage={registerBackground}
      >
        <form onSubmit={handleSubmit} className="space-y-5">
          <div className="flex items-start gap-3 border border-primary/35 bg-primary/10 px-4 py-3 text-sm text-[#f2f2f2]">
            <Gift size={18} className="mt-0.5 shrink-0 text-primary" />
            <div>
              <p className="font-semibold text-white">Signup bonus: free 1-day featured ad</p>
              <p className="mt-1 text-xs leading-5 text-[#bbbbbb]">
                Your credit is added automatically after registration.
              </p>
            </div>
          </div>

          <div>
            <label htmlFor="name" className="text-xs font-bold uppercase tracking-widest text-[#bbbbbb]">
              Display name
            </label>
            <input
              id="name"
              type="text"
              autoComplete="name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              required
              className="mt-2 h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
              placeholder="DJ name or full name"
            />
          </div>

          <div>
            <label htmlFor="register-email" className="text-xs font-bold uppercase tracking-widest text-[#bbbbbb]">
              Email
            </label>
            <input
              id="register-email"
              type="email"
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              required
              className="mt-2 h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
              placeholder="you@example.com"
            />
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="register-password" className="text-xs font-bold uppercase tracking-widest text-[#bbbbbb]">
                Password
              </label>
              <input
                id="register-password"
                type="password"
                autoComplete="new-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                minLength={8}
                required
                className="mt-2 h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
                placeholder="8+ characters"
              />
            </div>
            <div>
              <label htmlFor="password-confirmation" className="text-xs font-bold uppercase tracking-widest text-[#bbbbbb]">
                Confirm
              </label>
              <input
                id="password-confirmation"
                type="password"
                autoComplete="new-password"
                value={passwordConfirmation}
                onChange={(event) => setPasswordConfirmation(event.target.value)}
                minLength={8}
                required
                className="mt-2 h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
                placeholder="Repeat password"
              />
            </div>
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
            <UserPlus size={17} />
            {isSubmitting ? 'Creating Account...' : 'Create Account'}
          </button>
        </form>
      </AuthCard>
    </>
  );
}

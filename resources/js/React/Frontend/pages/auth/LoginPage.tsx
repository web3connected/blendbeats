import { Helmet } from '@dr.pogodin/react-helmet';
import { Eye, EyeOff, LogIn } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';

import AuthCard from '@/components/auth/AuthCard';
import { useAuth } from '@/components/auth/AuthProvider';
import { ApiAuthError } from '@/lib/auth';

const loginBackground = new URL('../../public/assets/images/login-bg.jpg', import.meta.url).href;

export default function LoginPage() {
  const navigate = useNavigate();
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError('');
    setIsSubmitting(true);

    try {
      await login(email, password);
      navigate('/account');
    } catch (submissionError) {
      setError(
        submissionError instanceof ApiAuthError
          ? submissionError.message
          : 'Unable to sign in right now.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Login | The Blend Battlegrounds</title>
        <meta name="description" content="Log in to The Blend Battlegrounds." />
      </Helmet>
      <AuthCard
        eyebrow="Back to the booth"
        title="Log In"
        subtitle="Jump back into battles, mixes, ratings, and the account tools that keep your DJ profile moving."
        footerPrompt="New to the Battlegrounds?"
        footerAction="Create an account"
        footerHref="/register"
        backgroundImage={loginBackground}
        backgroundImageClassName="opacity-100"
        backgroundOverlayClassName="from-[#0a0a0a]/55 via-[#0a0a0a]/20 to-transparent"
        backgroundBottomOverlayClassName="from-[#0a0a0a]/25 via-transparent to-transparent"
      >
        <form onSubmit={handleSubmit} className="space-y-5">
          <div>
            <label htmlFor="email" className="text-xs font-bold uppercase tracking-widest text-[#bbbbbb]">
              Email
            </label>
            <input
              id="email"
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
            <div className="flex items-center justify-between gap-3">
              <label htmlFor="password" className="text-xs font-bold uppercase tracking-widest text-[#bbbbbb]">
                Password
              </label>
              <Link to="/forgot-password" className="text-xs font-medium text-primary hover:text-primary/80">
                Forgot password?
              </Link>
            </div>
            <div className="relative mt-2">
              <input
                id="password"
                type={showPassword ? 'text' : 'password'}
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
                className="h-12 w-full border border-[#333333] bg-[#080808] px-4 pr-12 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
                placeholder="Your password"
              />
              <button
                type="button"
                onClick={() => setShowPassword((value) => !value)}
                className="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-[#888888] transition-colors hover:text-white"
                aria-label={showPassword ? 'Hide password' : 'Show password'}
              >
                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
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
            <LogIn size={17} />
            {isSubmitting ? 'Signing In...' : 'Sign In'}
          </button>
        </form>
      </AuthCard>
    </>
  );
}

import { Helmet } from '@dr.pogodin/react-helmet';
import { CheckCircle2, KeyRound, Loader2, Lock, ShieldCheck } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { changeAccountPassword } from '@/lib/account';

export default function SecurityPage() {
  const { user, isLoading } = useAuth();
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  if (isLoading) {
    return <main className="min-h-[60vh] bg-[#0a0a0a]" />;
  }

  if (!user) return <Navigate to="/login" replace />;

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setMessage('');
    setError('');

    if (password !== confirmation) {
      setError('The new password confirmation does not match.');
      return;
    }

    setIsSaving(true);
    try {
      setMessage(await changeAccountPassword({
        current_password: currentPassword,
        password,
        password_confirmation: confirmation,
      }));
      setCurrentPassword('');
      setPassword('');
      setConfirmation('');
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : 'Your password could not be updated.');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Security | The Blend Battlegrounds</title>
        <meta name="description" content="Manage your BlendBeats password and account security." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-12 text-white lg:px-8 lg:py-16">
        <div className="container mx-auto max-w-4xl">
          <p className="text-xs font-bold uppercase tracking-[0.25em] text-primary">Account / Security</p>
          <h1 className="mt-3 uppercase leading-none" style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 9vw, 7rem)' }}>
            Security
          </h1>
          <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
            Change your password using your current credentials. Use at least eight characters and avoid passwords used on other sites.
          </p>

          <div className="mt-10 grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
            <form onSubmit={handleSubmit} className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
              <div className="mb-6 flex items-center gap-3 border-b border-[#2a2a2a] pb-5">
                <div className="flex h-11 w-11 items-center justify-center bg-primary text-white"><KeyRound size={19} /></div>
                <div>
                  <p className="text-xs font-bold uppercase tracking-widest text-primary">Password</p>
                  <h2 className="mt-1 text-2xl uppercase" style={{ fontFamily: 'var(--font-heading)' }}>Change Password</h2>
                </div>
              </div>

              <div className="grid gap-4">
                <PasswordField label="Current Password" value={currentPassword} onChange={setCurrentPassword} autoComplete="current-password" />
                <PasswordField label="New Password" value={password} onChange={setPassword} autoComplete="new-password" />
                <PasswordField label="Confirm New Password" value={confirmation} onChange={setConfirmation} autoComplete="new-password" />
              </div>

              {error && <div className="mt-5 border border-primary/40 bg-primary/10 p-4 text-sm text-primary">{error}</div>}
              {message && <div className="mt-5 flex items-center gap-2 border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm text-emerald-300"><CheckCircle2 size={16} />{message}</div>}

              <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-between">
                <Link to="/account/settings" className="inline-flex h-11 items-center justify-center border border-[#333] px-5 text-xs font-bold uppercase tracking-widest text-[#ddd] hover:border-primary hover:text-primary">Back To Settings</Link>
                <button type="submit" disabled={isSaving || !currentPassword || password.length < 8 || !confirmation} className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest disabled:cursor-not-allowed disabled:opacity-50">
                  {isSaving ? <Loader2 className="animate-spin" size={15} /> : <Lock size={15} />}
                  {isSaving ? 'Updating' : 'Update Password'}
                </button>
              </div>
            </form>

            <aside className="border border-[#2a2a2a] bg-[#111111] p-5">
              <ShieldCheck size={24} className="text-[#FFB800]" />
              <h2 className="mt-5 text-2xl uppercase" style={{ fontFamily: 'var(--font-heading)' }}>Security Tips</h2>
              <ul className="mt-4 space-y-3 text-sm leading-6 text-[#999]">
                <li>Use a unique password for BlendBeats.</li>
                <li>Never share login or payment credentials.</li>
                <li>Sign out on shared devices.</li>
              </ul>
            </aside>
          </div>
        </div>
      </main>
    </>
  );
}

function PasswordField({ label, value, onChange, autoComplete }: { label: string; value: string; onChange: (value: string) => void; autoComplete: string }) {
  return (
    <label className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#999]">{label}</span>
      <input type="password" required value={value} onChange={(event) => onChange(event.target.value)} autoComplete={autoComplete} className="h-12 border border-[#333] bg-[#080808] px-4 text-sm text-white outline-none focus:border-primary" />
    </label>
  );
}

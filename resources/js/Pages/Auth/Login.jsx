import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Brand, Eyebrow, ScoreBadge } from '@/Components/ui';

function ProviderButton({ onClick, icon, label, badge }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="w-full bg-paper border border-line-strong rounded-[4px] px-3.5 py-[11px] font-sans text-sm font-medium text-ink cursor-pointer flex items-center justify-center gap-2.5 transition-colors duration-100 hover:bg-paper-2 hover:border-stone-400"
        >
            <span className="w-4 h-4 flex-shrink-0">{icon}</span>
            <span>{label}</span>
            <span className="ml-auto font-mono text-[10.5px] text-stone-500 uppercase tracking-[0.06em] font-normal">
                {badge}
            </span>
        </button>
    );
}

const GoogleIcon = (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="w-full h-full">
        <path d="M15.5 8.18c0-.56-.05-1.1-.14-1.6H8v3.04h4.2a3.6 3.6 0 0 1-1.55 2.36v1.96h2.5c1.46-1.35 2.3-3.34 2.3-5.76z" fill="#4285F4"/>
        <path d="M8 16c2.1 0 3.86-.7 5.15-1.9l-2.5-1.95c-.7.46-1.58.74-2.65.74-2.03 0-3.76-1.37-4.37-3.22H1.04v2.02A7.99 7.99 0 0 0 8 16z" fill="#34A853"/>
        <path d="M3.63 9.67a4.8 4.8 0 0 1 0-3.34V4.32H1.04a8 8 0 0 0 0 7.36z" fill="#FBBC05"/>
        <path d="M8 3.16c1.14 0 2.16.4 2.96 1.17l2.22-2.22A7.95 7.95 0 0 0 8 0a7.99 7.99 0 0 0-6.96 4.32l2.59 2.01C4.24 4.53 5.97 3.16 8 3.16z" fill="#EA4335"/>
    </svg>
);

const MailIcon = (
    <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="w-full h-full">
        <rect x="2" y="3.5" width="12" height="9" rx="1"/>
        <path d="M2 4.5l6 4.5 6-4.5"/>
    </svg>
);

export default function Login({ status, canResetPassword }) {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const hasError = errors.email || errors.password;

    return (
        <>
            <Head title="Sign in — Prospect Scanner" />
            <main
                className="min-h-screen grid"
                style={{ gridTemplateColumns: '1fr 1.05fr' }}
            >
                {/* ── LEFT: form ── */}
                <section className="bg-paper flex flex-col px-14 py-8 border-r border-line">

                    {/* topbar */}
                    <div className="flex justify-between items-center mb-auto">
                        <Brand href="/" />
                        <span className="font-mono text-[10.5px] text-stone-500 uppercase tracking-[0.1em] inline-flex items-center gap-2" title="All systems online">
                            <span className="w-1.5 h-1.5 rounded-full bg-positive" />
                            APIs online
                        </span>
                    </div>

                    {/* form wrapper */}
                    <div className="w-full max-w-[380px] mx-auto my-20">
                        <Eyebrow>Operator sign-in</Eyebrow>

                        <h1 className="font-serif font-normal text-[42px] leading-[1.05] tracking-[-0.018em] m-0 mb-3.5 text-ink">
                            Welcome back.
                        </h1>

                        <p className="text-stone-700 text-[14.5px] leading-[1.55] m-0 mb-8">
                            Three new audits ran overnight.{' '}
                            <strong className="font-medium text-ink">Briar &amp; Wren Solicitors</strong>{' '}
                            opened their report twice this morning.
                        </p>

                        {/* status message (e.g. password reset sent) */}
                        {status && (
                            <div className="flex items-start gap-2.5 bg-positive-soft border border-positive/20 rounded-[4px] px-3 py-2.5 pl-3.5 text-positive text-[12.5px] leading-[1.5] mb-[18px]">
                                <span className="w-1.5 h-1.5 bg-current mt-[7px] flex-shrink-0" aria-hidden="true" />
                                <span>{status}</span>
                            </div>
                        )}

                        {/* error banner */}
                        {hasError && (
                            <div className="flex items-start gap-2.5 bg-sev-critical-soft border border-sev-critical/20 rounded-[4px] px-3 py-2.5 pl-3.5 text-sev-critical text-[12.5px] leading-[1.5] mb-[18px]">
                                <span className="w-1.5 h-1.5 bg-current mt-[7px] flex-shrink-0" aria-hidden="true" />
                                <span>{errors.email || errors.password}</span>
                            </div>
                        )}

                        {/* SSO providers */}
                        <div className="flex flex-col gap-2 mb-[22px]">
                            <ProviderButton icon={GoogleIcon} label="Continue with Google Workspace" badge="SSO" />
                            <ProviderButton icon={MailIcon} label="Email me a sign-in link" badge="Magic link" />
                        </div>

                        {/* separator */}
                        <div className="flex items-center gap-3.5 my-[18px] font-mono text-[10.5px] text-stone-500 tracking-[0.1em] uppercase">
                            <span className="flex-1 h-px bg-line" />
                            or with password
                            <span className="flex-1 h-px bg-line" />
                        </div>

                        {/* form */}
                        <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
                            {/* email */}
                            <div className="flex flex-col gap-1.5">
                                <label htmlFor="email" className="text-xs font-medium text-stone-700">
                                    Work email
                                </label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="alex@nthdesigns.co.uk"
                                    autoComplete="username"
                                    required
                                    style={{ fontFamily: 'var(--font-sans)' }}
                                    className="text-sm text-ink bg-paper border border-line-strong rounded-[4px] px-3 py-2.5 w-full transition-[border-color,box-shadow] duration-100 placeholder:text-stone-400 focus:outline-none focus:border-accent-deep focus:[box-shadow:0_0_0_3px_var(--color-accent-soft)]"
                                />
                            </div>

                            {/* password */}
                            <div className="flex flex-col gap-1.5">
                                <div className="flex justify-between items-baseline">
                                    <label htmlFor="password" className="text-xs font-medium text-stone-700">
                                        Password
                                    </label>
                                    {canResetPassword && (
                                        <Link
                                            href={route('password.request')}
                                            className="font-mono text-[10.5px] text-stone-500 uppercase tracking-[0.06em] no-underline hover:text-accent-deep"
                                        >
                                            Forgot?
                                        </Link>
                                    )}
                                </div>
                                <div className="relative">
                                    <input
                                        id="password"
                                        name="password"
                                        type={showPassword ? 'text' : 'password'}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="••••••••••••"
                                        autoComplete="current-password"
                                        required
                                        style={{ fontFamily: 'var(--font-sans)' }}
                                        className="text-sm text-ink bg-paper border border-line-strong rounded-[4px] px-3 py-2.5 pr-16 w-full transition-[border-color,box-shadow] duration-100 placeholder:text-stone-400 focus:outline-none focus:border-accent-deep focus:[box-shadow:0_0_0_3px_var(--color-accent-soft)]"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((s) => !s)}
                                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 bg-transparent border-0 cursor-pointer text-stone-500 font-mono text-[10.5px] uppercase tracking-[0.08em] px-1.5 py-1 rounded-[2px] hover:text-ink hover:bg-stone-100 transition-colors"
                                    >
                                        {showPassword ? 'Hide' : 'Show'}
                                    </button>
                                </div>
                            </div>

                            {/* remember me */}
                            <div className="-mt-0.5">
                                <label className="inline-flex items-center gap-2 text-[12.5px] text-stone-700 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        id="remember"
                                        name="remember"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="w-[15px] h-[15px] flex-shrink-0 cursor-pointer accent-ink"
                                        style={{ accentColor: 'var(--color-ink)' }}
                                    />
                                    Keep me signed in for 30 days
                                </label>
                            </div>

                            {/* submit */}
                            <button
                                type="submit"
                                disabled={processing}
                                className="font-sans text-sm font-medium px-[18px] py-3 rounded-[4px] border-0 cursor-pointer bg-ink text-paper transition-[background-color,transform] duration-100 inline-flex items-center justify-center gap-2 w-full mt-1 hover:bg-stone-800 hover:-translate-y-px disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:translate-y-0 disabled:hover:bg-ink"
                            >
                                {processing ? (
                                    <>
                                        <span className="inline-block w-[13px] h-[13px] border-[1.6px] border-white/25 border-t-white rounded-full animate-spin" />
                                        Signing in…
                                    </>
                                ) : (
                                    <>
                                        <span>Sign in</span>
                                        <span className="font-mono">→</span>
                                    </>
                                )}
                            </button>
                        </form>

                        <div className="mt-[22px] pt-[18px] border-t border-line text-[13px] text-stone-600 flex justify-between items-center gap-3 flex-wrap">
                            <span>
                                No account?{' '}
                                <a href="mailto:ross@nthdesigns.co.uk" className="text-ink hover:text-accent-deep">
                                    Request operator access →
                                </a>
                            </span>
                            <span className="font-mono text-[10px] text-stone-500 uppercase tracking-[0.1em]">v0.4.2</span>
                        </div>
                    </div>

                    {/* page footer */}
                    <div className="mt-auto pt-8 flex justify-between items-center flex-wrap gap-3 font-mono text-[10.5px] text-stone-500 tracking-[0.06em]">
                        <span>© 2026 nthdesigns Ltd · Operator console</span>
                        <div className="flex gap-4">
                            <a href="#" className="text-stone-600 hover:text-ink">Status</a>
                            <a href="#" className="text-stone-600 hover:text-ink">Changelog</a>
                            <a href="#" className="text-stone-600 hover:text-ink">Support</a>
                        </div>
                    </div>
                </section>

                {/* ── RIGHT: editorial ── */}
                <aside
                    className="flex-col px-14 py-8 relative overflow-hidden hidden sm:flex"
                    style={{
                        background: 'radial-gradient(1200px 600px at 100% 0%, oklch(0.95 0.025 75) 0%, transparent 70%), var(--color-paper-2)',
                    }}
                >
                    {/* subtle grid overlay */}
                    <div
                        className="absolute inset-0 pointer-events-none"
                        style={{
                            backgroundImage: `
                                repeating-linear-gradient(0deg, transparent 0, transparent 39px, oklch(0 0 0 / 0.025) 39px, oklch(0 0 0 / 0.025) 40px),
                                repeating-linear-gradient(90deg, transparent 0, transparent 39px, oklch(0 0 0 / 0.025) 39px, oklch(0 0 0 / 0.025) 40px)
                            `,
                            maskImage: 'linear-gradient(180deg, black 0%, transparent 70%)',
                            WebkitMaskImage: 'linear-gradient(180deg, black 0%, transparent 70%)',
                        }}
                    />

                    <div className="my-auto max-w-[460px] relative z-10">
                        <Eyebrow>From last week's audits</Eyebrow>

                        <h2 className="font-serif font-normal text-[44px] leading-[1.05] tracking-[-0.022em] mt-[18px] mb-7 text-ink">
                            23 audits ran while you slept.{' '}
                            <em className="italic text-accent-deep">Five came back warm.</em>
                        </h2>

                        <p className="font-serif text-[19px] leading-[1.55] text-stone-700 m-0 mb-6">
                            <span className="font-serif text-accent text-[32px] leading-none mr-0.5 align-[-8px]">"</span>
                            The Scanner is a quiet workspace. Sign in, work through the warm leads first, and leave again. No dashboards to maintain, no streaks to keep.
                        </p>

                        {/* recent warm leads */}
                        <div className="mt-9 pt-6 border-t border-line">
                            <div className="font-mono text-[10px] text-stone-500 uppercase tracking-[0.12em] mb-3.5">
                                — Recent warm leads
                            </div>
                            <div className="flex flex-col gap-3">
                                {[
                                    { who: 'Briar & Wren Solicitors', what: 'Manchester · viewed 30 min ago', score: 93 },
                                    { who: 'Birmingham Dental Practice', what: 'Birmingham · viewed 2 h ago', score: 87 },
                                    { who: 'Hartley & Co Solicitors', what: 'Manchester · viewed 5 h ago', score: 74 },
                                ].map((item) => (
                                    <div key={item.who} className="flex justify-between items-center px-3 py-2.5 bg-paper border border-line rounded-[4px] gap-3.5">
                                        <div className="min-w-0">
                                            <div className="text-[12.5px] font-medium leading-[1.3] text-ink truncate">{item.who}</div>
                                            <div className="font-mono text-[10.5px] text-stone-500 mt-0.5">{item.what}</div>
                                        </div>
                                        <ScoreBadge value={item.score} withBar={false} />
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="mt-auto pt-8 font-mono text-[10.5px] text-stone-500 tracking-[0.06em] relative z-10 flex justify-between">
                        <span>scanner.nthdesigns.co.uk · UK-hosted</span>
                        <span>Authenticated session · 8 h timeout</span>
                    </div>
                </aside>

            </main>

            <style>{`
                @media (max-width: 900px) {
                    main { grid-template-columns: 1fr !important; }
                    aside { display: none !important; }
                    section { padding-left: 24px; padding-right: 24px; }
                }
            `}</style>
        </>
    );
}

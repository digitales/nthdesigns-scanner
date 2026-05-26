import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Button,
    Field,
    PageHeader,
    Status,
} from '@/Components/ui';

const SCAN_TYPES = [
    { value: 'gbp_only', title: 'GBP only', sub: 'Visibility audit · ~30s/biz' },
    { value: 'accessibility_only', title: 'Accessibility only', sub: 'WCAG 2.2 audit · ~90s/biz' },
    { value: 'combined', title: 'Combined', sub: 'Both signals · ~2m/biz' },
];

const SCAN_INFO = {
    gbp_only: 'Discovers businesses on Google, then scores each Google Business Profile. Typically completes in under a minute per business.',
    accessibility_only: 'Runs a full WCAG 2.2 audit on each website. Allow around 90 seconds per business.',
    combined: 'GBP scoring plus accessibility audit in sequence. Plan for roughly two minutes per business.',
};

export default function SearchIndex({ recentSearches, defaults = { country: 'GB' } }) {
    const { data, setData, post, processing, errors } = useForm({
        niche: '',
        city: '',
        country: defaults.country,
        scan_type: 'combined',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/searches');
    };

    return (
        <AuthenticatedLayout>
            <Head title="New search" />

            <main className="page" style={{ maxWidth: 1160 }}>
                <PageHeader
                    eyebrow="A · New search"
                    title="Run a prospect scan."
                    sub="We'll discover up to 25 businesses on Google, score their Google Business Profile, then audit their websites for WCAG 2.2 accessibility violations."
                />

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: 40 }}>
                    <div className="card card-pad">
                        <div className="card-title" style={{ marginBottom: 18 }}>Parameters</div>

                        <form onSubmit={submit}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 18 }}>
                                <Field label="Niche" hint="local trade or profession">
                                    <input
                                        className="input"
                                        value={data.niche}
                                        onChange={(e) => setData('niche', e.target.value)}
                                        placeholder="e.g. Dental practice"
                                        required
                                    />
                                    {errors.niche && <p className="text-xs text-sev-critical mt-1">{errors.niche}</p>}
                                </Field>
                                <Field label="City" hint="UK city or town">
                                    <input
                                        className="input"
                                        value={data.city}
                                        onChange={(e) => setData('city', e.target.value)}
                                        placeholder="e.g. Birmingham"
                                        required
                                    />
                                    {errors.city && <p className="text-xs text-sev-critical mt-1">{errors.city}</p>}
                                </Field>
                            </div>

                            <div style={{ marginBottom: 24 }}>
                                <Field label="Country">
                                    <select
                                        className="select"
                                        value={data.country}
                                        onChange={(e) => setData('country', e.target.value)}
                                    >
                                        <option value="GB">United Kingdom</option>
                                        <option value="IE">Ireland</option>
                                        <option value="US">United States</option>
                                    </select>
                                </Field>
                            </div>

                            <Field label="Scan type">
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginTop: 4 }}>
                                    {SCAN_TYPES.map((o) => (
                                        <button
                                            key={o.value}
                                            type="button"
                                            className={`scan-type-btn${data.scan_type === o.value ? ' active' : ''}`}
                                            onClick={() => setData('scan_type', o.value)}
                                        >
                                            <div style={{ fontSize: 13, fontWeight: 500, marginBottom: 4 }}>{o.title}</div>
                                            <div className="sub">{o.sub}</div>
                                        </button>
                                    ))}
                                </div>
                            </Field>

                            <div
                                style={{
                                    marginTop: 24,
                                    padding: '14px 16px',
                                    background: 'var(--color-paper-2)',
                                    borderRadius: 4,
                                    border: '1px solid var(--color-line)',
                                    fontSize: 13,
                                    color: 'var(--color-stone-600)',
                                    lineHeight: 1.55,
                                }}
                            >
                                {SCAN_INFO[data.scan_type]}
                            </div>

                            <div style={{ marginTop: 24 }}>
                                <Button kind="primary" size="lg" type="submit" disabled={processing} className="w-full justify-center">
                                    {processing ? 'Starting scan…' : 'Run scan'}
                                </Button>
                            </div>
                        </form>
                    </div>

                    <aside>
                        <div className="card-title" style={{ marginBottom: 12 }}>Recent searches</div>
                        {recentSearches.length === 0 ? (
                            <p className="micro">No searches yet.</p>
                        ) : (
                            <ul style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                {recentSearches.slice(0, 4).map((s) => (
                                    <li key={s.id}>
                                        <Link
                                            href={`/searches/${s.id}`}
                                            className="card card-pad"
                                            style={{
                                                display: 'block',
                                                textDecoration: 'none',
                                                color: 'inherit',
                                                padding: '12px 14px',
                                            }}
                                        >
                                            <div style={{ fontWeight: 500, fontSize: 13 }}>{s.niche}</div>
                                            <div className="micro" style={{ marginTop: 4 }}>
                                                {s.city} · {s.created_at}
                                            </div>
                                            <div style={{ marginTop: 8 }}>
                                                <SearchStatus status={s.status} />
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </aside>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

function SearchStatus({ status }) {
    const map = {
        pending: ['pending', 'Queued'],
        discovering: ['pending', 'Discovering'],
        auditing: ['pending', 'Auditing'],
        complete: ['ready', 'Complete'],
        failed: ['failed', 'Failed'],
    };
    const [kind, label] = map[status] ?? map.pending;
    return <Status kind={kind}>{label}</Status>;
}

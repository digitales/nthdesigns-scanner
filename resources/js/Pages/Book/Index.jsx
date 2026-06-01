import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

const EMBED_SCRIPT_SRC = 'https://tidycal.com/js/embed.js';

export default function BookIndex({ embedPath }) {
    useEffect(() => {
        if (!embedPath) {
            return;
        }

        if (document.querySelector(`script[src="${EMBED_SCRIPT_SRC}"]`)) {
            return;
        }

        const script = document.createElement('script');
        script.src = EMBED_SCRIPT_SRC;
        script.async = true;
        document.body.appendChild(script);
    }, [embedPath]);

    return (
        <>
            <Head title="Book a review call" />

            <div className="public-report-wrap">
                <article className="public-report book-page">
                    <header style={{ padding: '56px 80px 32px', borderBottom: '1px solid var(--color-line)' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 40 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                <span className="brand-mark" style={{ width: 22, height: 22 }} />
                                <span style={{ fontFamily: 'var(--font-serif)', fontStyle: 'italic', fontSize: 17 }}>nthdesigns</span>
                            </div>
                            <div className="micro" style={{ textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                                Scheduling
                            </div>
                        </div>

                        <div className="eyebrow" style={{ marginBottom: 14, color: 'var(--color-accent-deep)' }}>Next step</div>
                        <h1 style={{
                            fontFamily: 'var(--font-serif)',
                            fontWeight: 400,
                            fontSize: 48,
                            lineHeight: 1.1,
                            letterSpacing: '-0.02em',
                            margin: '0 0 14px',
                        }}>
                            Book a free 30-minute review
                        </h1>
                        <p style={{
                            fontFamily: 'var(--font-serif)',
                            fontSize: 18,
                            color: 'var(--color-stone-600)',
                            maxWidth: 520,
                            margin: 0,
                            lineHeight: 1.55,
                        }}>
                            Choose a time that suits you. We will walk through the audit findings and outline what fixing them would involve — no obligation.
                        </p>
                    </header>

                    <section className="book-embed-section" style={{ padding: '32px 80px 56px' }}>
                        <div className="tidycal-embed" data-path={embedPath} />
                        <p className="micro" style={{ marginTop: 24, textAlign: 'center' }}>
                            Typical reply within one working day
                        </p>
                    </section>

                    <footer style={{ padding: '32px 80px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid var(--color-line)' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span className="brand-mark" style={{ width: 14, height: 14 }} />
                            <span className="micro">nthdesigns · Digital consultancy</span>
                        </div>
                    </footer>
                </article>
            </div>
        </>
    );
}

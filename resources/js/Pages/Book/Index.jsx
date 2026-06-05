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
                    <header>
                        <div className="public-report-header-bar">
                            <div className="public-report-brand">
                                <span className="brand-mark brand-mark--md" />
                                <span className="public-report-brand-name">nthdesigns</span>
                            </div>
                            <div className="micro micro--upper">
                                Scheduling
                            </div>
                        </div>

                        <div className="eyebrow eyebrow--spaced eyebrow--accent">Next step</div>
                        <h1 className="public-report-title public-report-title--book">
                            Book a free 30-minute review
                        </h1>
                        <p className="public-report-book-lede">
                            Choose a time that suits you. We will walk through the audit findings and outline what fixing them would involve — no obligation.
                        </p>
                    </header>

                    <section className="book-embed-section">
                        <div className="tidycal-embed" data-path={embedPath} />
                        <p className="micro public-report-embed-note">
                            Typical reply within one working day
                        </p>
                    </section>

                    <footer className="public-report-footer--bordered">
                        <div className="public-report-footer-brand">
                            <span className="brand-mark brand-mark--sm" />
                            <span className="micro">nthdesigns · Digital consultancy</span>
                        </div>
                    </footer>
                </article>
            </div>
        </>
    );
}

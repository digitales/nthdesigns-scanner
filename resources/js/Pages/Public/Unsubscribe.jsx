import { Head } from '@inertiajs/react';

export default function Unsubscribe({ success, message }) {
    return (
        <>
            <Head title={success ? 'Unsubscribed' : 'Unsubscribe link invalid'} />

            <div className="public-report-wrap">
                <article className="public-report" style={{ maxWidth: '32rem', margin: '4rem auto', padding: '2rem' }}>
                    <header className="mb-16">
                        <div className="public-report-brand">
                            <span className="brand-mark brand-mark--md" />
                            <span className="public-report-brand-name">nthdesigns</span>
                        </div>
                    </header>

                    <h1 style={{ fontSize: '1.25rem', marginBottom: '0.75rem' }}>
                        {success ? 'Unsubscribed' : 'Link not valid'}
                    </h1>
                    <p className="micro">{message}</p>
                </article>
            </div>
        </>
    );
}

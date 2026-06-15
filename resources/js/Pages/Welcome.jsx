import { Head } from '@inertiajs/react';
import HomePage from './Welcome/HomePage';
import '../../css/homepage.css';

export default function Welcome({ canLogin, canRegister, homepageAuditEnabled = true }) {
    return (
        <>
            <Head>
                <title>nthdesigns Prospect Scanner — the audit your customers wish you'd run</title>
                <meta
                    name="description"
                    content="WCAG 2.2 and Google Business Profile audits for UK SMEs. Written report in 90 seconds. No login required."
                />
            </Head>
            <div className="marketing-page">
                <HomePage
                    canLogin={canLogin}
                    canRegister={canRegister}
                    homepageAuditEnabled={homepageAuditEnabled}
                />
            </div>
        </>
    );
}

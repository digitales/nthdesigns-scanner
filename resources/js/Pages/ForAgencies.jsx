import { Head } from '@inertiajs/react';
import AgencyPage from './ForAgencies/AgencyPage';
import '../../css/homepage.css';

export default function ForAgencies({ canLogin, canRegister }) {
    return (
        <>
            <Head>
                <title>Prospect Scanner for agencies — find, prove, and convert local leads</title>
                <meta
                    name="description"
                    content="Dual-signal prospecting for UK agencies: Google Business Profile + WCAG audits, shareable reports, and AI outreach in one workflow."
                />
            </Head>
            <div className="marketing-page">
                <AgencyPage canLogin={canLogin} canRegister={canRegister} />
            </div>
        </>
    );
}

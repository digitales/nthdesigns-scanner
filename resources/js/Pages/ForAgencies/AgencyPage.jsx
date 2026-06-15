import { useCallback } from 'react';
import AgencyNav from './components/AgencyNav';
import AgencyHero from './components/AgencyHero';
import AgencyWorkflow from './components/AgencyWorkflow';
import AgencyFeatures from './components/AgencyFeatures';
import AgencyCompare from './components/AgencyCompare';
import AgencyPricing from './components/AgencyPricing';
import AgencyAudience from './components/AgencyAudience';
import AgencyFAQ from './components/AgencyFAQ';
import AgencyFooterCTA from './components/AgencyFooterCTA';
import SiteFooter from '../Welcome/components/SiteFooter';

export default function AgencyPage({ canLogin, canRegister }) {
  const scrollTo = useCallback((id) => {
    const el = document.getElementById(id);
    if (el) {
      const top = el.getBoundingClientRect().top + window.scrollY - 88;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  }, []);

  return (
    <div className="page-frame">
      <AgencyNav scrollTo={scrollTo} canLogin={canLogin} canRegister={canRegister} />
      <AgencyHero scrollTo={scrollTo} />
      <AgencyWorkflow />
      <AgencyFeatures />
      <AgencyCompare />
      <AgencyPricing scrollTo={scrollTo} />
      <AgencyAudience />
      <AgencyFAQ />
      <AgencyFooterCTA scrollTo={scrollTo} canRegister={canRegister} />
      <SiteFooter scrollTo={scrollTo} marketingPath="/for-agencies" />
    </div>
  );
}

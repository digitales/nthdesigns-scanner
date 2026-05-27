import { useCallback } from 'react';
import Nav from './components/Nav';
import HeroEditorial from './components/HeroEditorial';
import HowItWorks from './components/HowItWorks';
import SampleReportExcerpt from './components/SampleReportExcerpt';
import WhyNow from './components/WhyNow';
import Evidence from './components/Evidence';
import Compare from './components/Compare';
import Testimonials from './components/Testimonials';
import Pricing from './components/Pricing';
import SelfCheck from './components/SelfCheck';
import FAQ from './components/FAQ';
import FooterCTA from './components/FooterCTA';
import SiteFooter from './components/SiteFooter';

export default function HomePage({ canLogin, canRegister }) {
  const scrollTo = useCallback((id) => {
    const el = document.getElementById(id);
    if (el) {
      const top = el.getBoundingClientRect().top + window.scrollY - 88;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  }, []);

  return (
    <div className="page-frame">
      <Nav scrollTo={scrollTo} canLogin={canLogin} canRegister={canRegister} />
      <HeroEditorial />
      <HowItWorks />
      <SampleReportExcerpt />
      <WhyNow />
      <Evidence />
      <Compare />
      <Pricing scrollTo={scrollTo} />
      <Testimonials />
      <SelfCheck />
      <FAQ />
      <FooterCTA scrollTo={scrollTo} />
      <SiteFooter scrollTo={scrollTo} />
    </div>
  );
}

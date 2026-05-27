import { Button } from '@/Components/ui';
import Arrow from './Arrow';

export default function FooterCTA({ scrollTo }) {
  return (
    <section className="footer-cta">
      <h2>Run the audit. <em>Read the report.</em><br />Decide afterwards.</h2>
      <p>It takes ninety seconds. No login. We don't capture an email unless you ask us to.</p>
      <Button type="button" kind="accent" size="lg" onClick={() => scrollTo('hero')}>Get your audit <Arrow /></Button>
      <div className="small">cal.nthdesigns.co.uk · usually replied to within 4 hours · UK working hours</div>
    </section>
  );
}

import { Button, LinkButton } from '@/Components/ui';
import Arrow from '../../Welcome/components/Arrow';

export default function AgencyFooterCTA({ scrollTo, canRegister }) {
  return (
    <section className="footer-cta" id="cta">
      <h2>See a live search, report, and outreach draft — <em>for your city.</em></h2>
      <p>
        20-minute walkthrough. We run a real niche scan, show the report your prospects would receive,
        and generate a sample outreach email with the report link embedded.
      </p>
      <div className="footer-cta-actions">
        {canRegister ? (
          <LinkButton href={route('register')} kind="accent" size="lg">
            Register for early access <Arrow />
          </LinkButton>
        ) : (
          <Button type="button" kind="accent" size="lg" onClick={() => scrollTo('hero')}>
            Request early access <Arrow />
          </Button>
        )}
        <a href="mailto:hello@nthdesigns.co.uk?subject=Prospect%20Scanner%20walkthrough" className="footer-cta-email mono">
          hello@nthdesigns.co.uk
        </a>
      </div>
      <div className="small">Early access · UK agencies and freelancers · no card required</div>
    </section>
  );
}

import { Button, LinkButton } from '@/Components/ui';
import Arrow from '../../Welcome/components/Arrow';

export default function AgencyPricing({ scrollTo }) {
  return (
    <section className="s full" id="pricing">
      <div className="inner">
        <div className="section-head">
          <div>
            <span className="eyebrow">Planned pricing</span>
            <h2 className="heading">Three tiers when we open externally — <em>validated internally first.</em></h2>
          </div>
          <p className="note">
            Prospect Scanner is production-deployed for nthdesigns today. External billing and
            onboarding are the remaining gap (~4–8 weeks). Register interest below for early access.
          </p>
        </div>

        <div className="pricing">
          <div className="price-card">
            <div className="pc-eyebrow">Solo</div>
            <h3>Freelancer</h3>
            <div className="price">£39<small>/month</small></div>
            <p className="price-desc">For local SEO freelancers and solo accessibility consultants.</p>
            <ul>
              <li>5 niche searches per day</li>
              <li>Dual-signal scoring + reports</li>
              <li>AI outreach generation</li>
              <li>1 user seat</li>
            </ul>
            <div className="pc-cta">
              <Button type="button" kind="secondary" onClick={() => scrollTo('cta')}>Register interest <Arrow /></Button>
            </div>
          </div>

          <div className="price-card featured">
            <div className="pc-eyebrow">Recommended</div>
            <h3>Agency</h3>
            <div className="price">£119<small>/month</small></div>
            <p className="price-desc">For small agencies running prospecting and outreach at scale.</p>
            <ul>
              <li>20 niche searches per day</li>
              <li>Niche opportunity scanner</li>
              <li>Prospect lists + warm lead queue</li>
              <li>3 user seats</li>
            </ul>
            <div className="pc-cta">
              <Button type="button" kind="primary" onClick={() => scrollTo('cta')}>Request early access <Arrow /></Button>
            </div>
          </div>

          <div className="price-card">
            <div className="pc-eyebrow">White-label</div>
            <h3>Partner</h3>
            <div className="price">£189<small>/month</small></div>
            <p className="price-desc">Custom branding on public audit reports and shared list sheets.</p>
            <ul>
              <li>Everything in Agency</li>
              <li>White-label report URLs</li>
              <li>Shared prospect sheets</li>
              <li>MCP agent integration</li>
            </ul>
            <div className="pc-cta">
              <Button type="button" kind="secondary" onClick={() => scrollTo('cta')}>Talk to us <Arrow /></Button>
            </div>
          </div>
        </div>

        <p className="pricing-footnote mono">
          Need audits for your own clients today?{' '}
          <LinkButton href="/" kind="ghost" size="sm">See SME audit pricing →</LinkButton>
        </p>
      </div>
    </section>
  );
}

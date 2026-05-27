import { Button } from '@/Components/ui';
import Arrow from './Arrow';

export default function Pricing({ scrollTo }) {
  return (
    <section className="s full" id="pricing">
      <div className="inner">
        <div className="section-head">
          <div>
            <span className="eyebrow">Engagement model</span>
            <h2 className="heading">Three ways in — none of them a per-month subscription.</h2>
          </div>
          <p className="note">
            Start with the free scan. Most prospects do; about a quarter come back for the
            fix package. We take three new retainers a quarter and not many more.
          </p>
        </div>

        <div className="pricing">
          <div className="price-card">
            <div className="pc-eyebrow">Tier 1 · scan</div>
            <h3>Free audit</h3>
            <div className="price">£0</div>
            <p className="price-desc">A scan and a shareable report. No login. No follow-up email unless you ask.</p>
            <ul>
              <li>Full WCAG 2.2 audit of your homepage and three deep pages</li>
              <li>Google Business Profile comparison against your nearest competitor</li>
              <li>Lighthouse Performance, SEO, Best Practices dials</li>
              <li>Shareable PDF and unlisted public URL</li>
            </ul>
            <div className="pc-cta">
              <Button type="button" kind="secondary" onClick={() => scrollTo('hero')}>Run a free audit <Arrow /></Button>
            </div>
          </div>

          <div className="price-card featured">
            <div className="pc-eyebrow">Tier 2 · most popular</div>
            <h3>Fix package</h3>
            <div className="price">£2,400 <small>one-off</small></div>
            <p className="price-desc">A full audit, a written fix brief for your developer, and a 90-minute walkthrough call.</p>
            <ul>
              <li>Everything in the free audit</li>
              <li>Manual audit of every booking and contact flow</li>
              <li>Written brief sized for a single developer sprint</li>
              <li>90-minute screen-share call to walk through the fix list</li>
              <li>Re-scan when the work ships, included</li>
            </ul>
            <div className="pc-cta">
              <Button kind="primary">Book the fix package <Arrow /></Button>
            </div>
          </div>

          <div className="price-card">
            <div className="pc-eyebrow">Tier 3 · retainer</div>
            <h3>Ongoing compliance</h3>
            <div className="price">£900<small>/month</small></div>
            <p className="price-desc">Quarterly re-audits, EHRC monitoring, on-call review of any new pages your team ships.</p>
            <ul>
              <li>Quarterly full audits</li>
              <li>Two hours of review time per month</li>
              <li>EHRC guidance change alerts, written for non-lawyers</li>
              <li>First-line response if a notice arrives</li>
            </ul>
            <div className="pc-cta">
              <Button kind="secondary">Talk about a retainer <Arrow /></Button>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

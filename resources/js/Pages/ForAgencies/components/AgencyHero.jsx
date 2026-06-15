import { Button } from '@/Components/ui';
import Arrow from '../../Welcome/components/Arrow';

export default function AgencyHero({ scrollTo }) {
  return (
    <div className="hero-editorial" id="hero">
      <div className="eyebrow-strip">
        <span className="hero-pill"><span className="led" />Agency preview · early access</span>
        <span className="eyebrow">Built by nthdesigns · for UK local-service prospecting</span>
      </div>
      <h1 className="display">
        Find weak local businesses. <em>Prove it. Close them.</em>
      </h1>
      <p className="lede">
        Prospect Scanner combines Google Business Profile scoring, WCAG accessibility audits,
        shareable client reports, and AI outreach in one workflow — so your pitch leads with
        evidence prospects can verify before they reply.
      </p>
      <div className="hero-actions">
        <Button type="button" kind="primary" size="lg" onClick={() => scrollTo('cta')}>
          Request early access <Arrow />
        </Button>
        <Button type="button" kind="secondary" size="lg" onClick={() => scrollTo('workflow')}>
          See the workflow
        </Button>
      </div>
      <p className="hero-footnote mono">
        Find → Score → Prove → Convert · dual-signal scoring · no dashboard login for prospects
      </p>
    </div>
  );
}

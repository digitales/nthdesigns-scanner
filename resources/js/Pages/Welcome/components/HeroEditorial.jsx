import AuditWidget from './AuditWidget';

export default function HeroEditorial() {
  return (
    <div className="hero-editorial" id="hero">
      <div className="eyebrow-strip">
        <span className="hero-pill"><span className="led" />New · 23 audits this week</span>
        <span className="eyebrow">An nthdesigns method · for UK SMEs</span>
      </div>
      <h1 className="display">
        The audit your customers <em>wish you'd run.</em>
      </h1>
      <p className="lede">
        We audit your website against WCAG 2.2 and your Google Business Profile against the
        best-performing competitor in your postcode. You get a written report, a fix list,
        and the option of a 30-minute call to walk through them. No retainer required.
      </p>
      <AuditWidget kind="primary" />
    </div>
  );
}

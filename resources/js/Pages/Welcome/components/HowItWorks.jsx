export default function HowItWorks() {
  return (
    <section className="s" id="how">
      <div className="section-head">
        <div>
          <span className="eyebrow">How it works</span>
          <h2 className="heading">Two signals, one report — because <em>neither alone is enough.</em></h2>
        </div>
        <p className="note">
          Most audit tools check one. Lighthouse covers the website; SEMrush covers Google;
          neither tells you which one is actually losing you bookings. We measure both,
          then tell you which to fix first.
        </p>
        <p className="note note-accent mono">
          Dental practices in Birmingham pay ~£6.50 per Google Ads click. A strong GBP delivers equivalent visibility organically.
        </p>
      </div>

      <div className="signals">
        <div className="signal-card">
          <div className="ix">Signal 1 · Website</div>
          <h3>WCAG 2.2 accessibility &amp; performance.</h3>
          <p>
            Headless Chrome plus axe-core walks every reachable page on your site.
            We mark up critical, serious, and moderate issues against the
            current WCAG standard and the EHRC's 2025 enforcement guidance.
          </p>
          <ul className="signal-list">
            <li>Contrast, alt text, heading order, focus visibility</li>
            <li>Form labels, error handling, keyboard reachability</li>
            <li>Lighthouse Performance, SEO, Best Practices scores</li>
            <li>Mobile-first — the way Google ranks since 2024</li>
          </ul>
        </div>

        <div className="signal-card">
          <div className="ix">Signal 2 · Google Business Profile</div>
          <h3>Visibility against the top competitor in your postcode.</h3>
          <p>
            We pull your profile via the Places API and the top three competitors
            in a five-kilometre radius. The comparison is what surfaces what to fix —
            not abstract "best practice", but what your nearest rival is doing better.
          </p>
          <ul className="signal-list">
            <li>Reviews, rating, response rate, response time</li>
            <li>Photos, posts, Q&amp;A, verified hours</li>
            <li>Service list completeness, booking link present</li>
            <li>The single competitor most likely to take your enquiry</li>
          </ul>
          <p className="signal-priority mono">We tell you whether to fix the website, the Google profile, or both first.</p>
        </div>
      </div>
    </section>
  );
}

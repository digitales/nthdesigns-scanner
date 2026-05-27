export default function WhyNow() {
  return (
    <section className="s">
      <div className="section-head">
        <div>
          <span className="eyebrow">Why now</span>
          <h2 className="heading">WCAG 2.2 is the new floor — and the EHRC is enforcing it.</h2>
        </div>
        <p className="note">
          Two things changed in 2025. The W3C published WCAG 2.2 with nine new criteria
          (mostly around drag, focus, and authentication). The UK's Equality and Human
          Rights Commission revised its enforcement guidance to include any business that
          accepts online instructions or bookings.
        </p>
      </div>

      <div className="why-now">
        <div>
          <p style={{ fontFamily: "var(--serif)", fontSize: 20, lineHeight: 1.5, color: "var(--stone-700)", margin: 0 }}>
            Public-sector bodies have been on this hook since 2018. From this year, professional
            service firms — dentists, solicitors, optometrists, accountants — are too. The first
            wave of EHRC notices went out in March; we've audited eleven of the firms that received them.
          </p>
          <p style={{ fontFamily: "var(--serif)", fontSize: 20, lineHeight: 1.5, color: "var(--stone-700)", marginTop: 24, marginBottom: 0 }}>
            None of this is theoretical. The cost of a single notice is roughly six months
            of our largest retainer. The cost of fixing it before one arrives is usually under
            half a day.
          </p>
        </div>

        <div className="key-dates">
          <div className="key-date">
            <div className="date">2024 <span>Oct</span></div>
            <div>
              <h4>WCAG 2.2 becomes the W3C recommendation</h4>
              <p>Adds nine criteria covering focus appearance, target size, drag movement, accessible authentication.</p>
            </div>
          </div>
          <div className="key-date">
            <div className="date">2025 <span>Jan</span></div>
            <div>
              <h4>EHRC publishes revised enforcement guidance</h4>
              <p>Online services that take instructions or bookings are explicitly in scope.</p>
            </div>
          </div>
          <div className="key-date">
            <div className="date">2025 <span>Mar</span></div>
            <div>
              <h4>First non-public-sector notices issued</h4>
              <p>Eleven UK firms received compliance notices. Eight had been audited in the previous year by an SEO tool that flagged none of the issues cited.</p>
            </div>
          </div>
          <div className="key-date">
            <div className="date">2026 <span>Aug</span></div>
            <div>
              <h4>Soft-enforcement window closes</h4>
              <p>EHRC has signalled fines rather than notices from August.</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

export default function Compare() {
  const rows = [
    { feature: "WCAG 2.2 accessibility audit",  note: "axe-core + manual review",       us: true, them: "Partial — surface checks only" },
    { feature: "Google Business Profile audit", note: "vs. nearest competitor",         us: true, them: false },
    { feature: "Lighthouse performance dials",  note: "Performance · SEO · Best Pract.", us: true, them: true },
    { feature: "Written report, not dashboard", note: "PDF + shareable URL",            us: true, them: "Dashboard only" },
    { feature: "UK EHRC enforcement context",   note: "drafted for this jurisdiction",  us: true, them: false },
    { feature: "30-minute call with auditor",   note: "free, no upsell",                us: true, them: false },
    { feature: "Maintained by accessibility consultancy", note: "not an AI start-up",   us: true, them: false },
    { feature: "Per-month subscription required", note: "",                             us: false, them: true },
  ];
  return (
    <section className="s" id="compare">
      <div className="section-head">
        <div>
          <span className="eyebrow">vs. generic audit tools</span>
          <h2 className="heading">Why a written report from a UK consultancy beats <em>another SaaS dashboard.</em></h2>
        </div>
        <p className="note">
          We're not faster. We're not cheaper per scan. We are a consultancy that audits
          eleven sites a week, by hand, against the regulation that the EHRC is actually
          enforcing this year. The comparison sites compare against a 2018 standard.
        </p>
      </div>

      <div className="compare">
        <div className="compare-head">
          <div>
            <span className="eyebrow no-rule">Capability</span>
          </div>
          <div>
            <span className="eyebrow no-rule">Generic SEO audit tool</span>
            <div className="name">Lighthouse / SEMrush / Ahrefs</div>
          </div>
          <div className="us">
            <span className="eyebrow no-rule" style={{ color: "var(--accent-ink)" }}><span>Us</span></span>
            <div className="name">nthdesigns Prospect Scanner</div>
          </div>
        </div>
        {rows.map((r, i) => (
          <div className="compare-row" key={i}>
            <div className="feature">
              {r.feature}
              {r.note && <div className="note">{r.note}</div>}
            </div>
            <div className="cell">
              {r.them === true ? <span className="check">✓</span> : r.them === false ? <span className="nope">—</span> : <span style={{ color: "var(--stone-700)", fontSize: 12.5 }}>{r.them}</span>}
            </div>
            <div className="cell us">
              {r.us === true ? <span className="check" style={{ color: "var(--accent-deep)" }}>✓</span> : r.us === false ? <span className="nope">—</span> : <span style={{ color: "var(--accent-ink)", fontSize: 12.5 }}>{r.us}</span>}
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

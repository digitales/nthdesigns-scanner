export default function AgencyCompare() {
  const rows = [
    { feature: 'Local prospect discovery', note: 'niche + city via Google Places', us: true, them: 'Manual / partial' },
    { feature: 'GBP vs competitor scoring', note: 'benchmark-relative gaps', us: true, them: false },
    { feature: 'WCAG accessibility audit', note: 'axe-core + Lighthouse + screenshots', us: true, them: 'Surface checks only' },
    { feature: 'Shareable client report', note: 'public link, no login', us: true, them: 'Dashboard only' },
    { feature: 'AI outreach with proof', note: 'report URL + pitch angle', us: true, them: false },
    { feature: 'Market triage scanner', note: 'rank niches before searching', us: true, them: false },
    { feature: 'Warm lead tracking', note: 'report viewed, no reply', us: true, them: false },
    { feature: 'Per-month subscription required', note: '', us: 'From £39/mo', them: true },
  ];

  return (
    <section className="s" id="compare">
      <div className="section-head">
        <div>
          <span className="eyebrow">vs generic SEO tools</span>
          <h2 className="heading">Prospecting workflow, not <em>another audit dashboard.</em></h2>
        </div>
        <p className="note">
          BrightLocal covers GBP. Semrush covers sites. Neither scores local prospects on both
          signals, generates outreach, and ships a verifiable report link in one pass.
        </p>
      </div>

      <div className="compare">
        <div className="compare-head">
          <div>
            <span className="eyebrow no-rule">Capability</span>
          </div>
          <div>
            <span className="eyebrow no-rule">Generic stack</span>
            <div className="name">BrightLocal / Semrush / Outscraper</div>
          </div>
          <div className="us">
            <span className="eyebrow no-rule"><span>Us</span></span>
            <div className="name">Prospect Scanner</div>
          </div>
        </div>
        {rows.map((r, i) => (
          <div className="compare-row" key={i}>
            <div className="feature">
              {r.feature}
              {r.note ? <div className="note">{r.note}</div> : null}
            </div>
            <div className="cell">
              {r.them === true ? <span className="check">✓</span> : r.them === false ? <span className="nope">—</span> : <span className="compare-cell-text">{r.them}</span>}
            </div>
            <div className="cell us">
              {r.us === true ? <span className="check us">✓</span> : r.us === false ? <span className="nope">—</span> : <span className="compare-cell-text us">{r.us}</span>}
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

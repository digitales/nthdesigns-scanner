const NICHES = [
  {
    label: 'Private dental & healthcare',
    detail: 'High transaction value, weak self-managed digital presence, and rising accessibility expectations from patients.',
  },
  {
    label: 'Legal & professional services',
    detail: 'Regulated firms with EU-facing services, online booking flows, and EHRC enforcement now in scope.',
  },
  {
    label: 'Independent hospitality',
    detail: 'GBP-dependent for local discovery — reviews, photos, and Maps placement drive bookings directly.',
  },
];

export default function WhoItsFor() {
  return (
    <section className="s full" id="who">
      <div className="inner">
        <div className="section-head">
          <div>
            <span className="eyebrow">Who this is for</span>
            <h2 className="heading">Professional firms where <em>both signals matter.</em></h2>
          </div>
          <p className="note">
            We audit any UK business, but the dual-signal report is strongest where online booking,
            Google Maps visibility, and accessibility compliance overlap.
          </p>
        </div>

        <div className="audience-grid">
          {NICHES.map((n) => (
            <article key={n.label} className="audience-card">
              <h3>{n.label}</h3>
              <p>{n.detail}</p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

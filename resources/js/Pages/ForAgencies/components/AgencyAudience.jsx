const AUDIENCES = [
  {
    title: 'Design & accessibility agencies',
    body: 'Pipeline for WCAG remediation and redesign work. Lead with verifiable violation counts under EAA and EHRC enforcement.',
    niches: 'Dental · Legal · Professional services',
  },
  {
    title: 'Local SEO freelancers',
    body: 'Find weak GBP profiles and pitch with competitor data. Optional CPC framing: what rivals pay per click vs organic Maps visibility.',
    niches: 'Trades · Hospitality · Health',
  },
  {
    title: 'Agency BD & VAs',
    body: 'Batch prospect, generate reports, draft outreach, and track warm leads without switching between five separate tools.',
    niches: 'Any UK local-service niche',
  },
];

export default function AgencyAudience() {
  return (
    <section className="s">
      <div className="section-head">
        <div>
          <span className="eyebrow">Who it&apos;s for</span>
          <h2 className="heading">Built for operators who <em>sell proof,</em> not opinions.</h2>
        </div>
        <p className="note">
          We recommend starting with one niche and one city — private dental in Birmingham is our
          current internal baseline — then expanding once reply rates stabilise.
        </p>
      </div>

      <div className="audience-grid">
        {AUDIENCES.map((a) => (
          <article key={a.title} className="audience-card">
            <h3>{a.title}</h3>
            <p>{a.body}</p>
            <div className="mono audience-niches">{a.niches}</div>
          </article>
        ))}
      </div>
    </section>
  );
}

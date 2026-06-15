const FEATURES = [
  {
    term: 'Niche opportunity scanner',
    detail: 'Survey 50+ niches across 100+ UK cities. Rank markets by GBP weakness, missing websites, and low reviews before running full searches.',
  },
  {
    term: 'Dual-signal scoring',
    detail: 'GBP absolute and benchmark-relative scoring combined with axe-core and Lighthouse accessibility. One weakness rank, one dominant pitch angle.',
  },
  {
    term: 'Shareable audit reports',
    detail: 'Unlisted public URLs with grade, violations, screenshots, and competitor comparison. The report is the sales document — no PDF export required.',
  },
  {
    term: 'AI outreach generation',
    detail: 'Claude-powered cold emails with pitch angle auto-resolved from scores. Bulk generate, queue, and track sent → viewed → replied.',
  },
  {
    term: 'Warm lead queue',
    detail: 'Prospects who opened the report after outreach but have not replied surface for follow-up. Report view counts and booking stats in one dashboard.',
  },
  {
    term: 'Pipeline CRM',
    detail: 'Manual and smart prospect lists, follow-up dates, status pipeline, tags, notes, and CSV export. Share curated sheets externally without contact details.',
  },
];

export default function AgencyFeatures() {
  return (
    <section className="s full" id="features">
      <div className="inner">
        <div className="section-head">
          <div>
            <span className="eyebrow">Capabilities</span>
            <h2 className="heading">Everything between <em>“who should we pitch?”</em> and <em>“they booked a call.”</em></h2>
          </div>
          <p className="note">
            Operator features stay on this page. The public SME homepage at nthdesigns.co.uk
            sells audit deliverables to business owners — not the prospecting tool itself.
          </p>
        </div>

        <dl className="feature-dl">
          {FEATURES.map((f) => (
            <div key={f.term} className="feature-dl-item">
              <dt>{f.term}</dt>
              <dd>{f.detail}</dd>
            </div>
          ))}
        </dl>
      </div>
    </section>
  );
}

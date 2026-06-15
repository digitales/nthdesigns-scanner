const STEPS = [
  {
    n: '01',
    title: 'Discover',
    body: 'Run a niche + city search or audit a single URL. Google Places returns local businesses; the niche scanner ranks markets before you commit spend.',
  },
  {
    n: '02',
    title: 'Score',
    body: 'Dual-signal weakness ranking: GBP gaps vs the local #1 competitor plus axe-core and Lighthouse violations. The system picks the dominant pitch angle.',
  },
  {
    n: '03',
    title: 'Prove',
    body: 'Auto-generate an unlisted report with grade, violations, screenshots, and competitor comparison. Prospects open it on their phone — no login.',
  },
  {
    n: '04',
    title: 'Convert',
    body: 'AI drafts personalised outreach with the report link embedded. Track views, warm leads, and bookings from the report CTA.',
  },
];

export default function AgencyWorkflow() {
  return (
    <section className="s" id="workflow">
      <div className="section-head">
        <div>
          <span className="eyebrow">Workflow</span>
          <h2 className="heading">From market scan to signed client — <em>without switching tools.</em></h2>
        </div>
        <p className="note">
          Most agencies stitch together Places scraping, Lighthouse, a CRM, and ChatGPT.
          Prospect Scanner runs the full loop: discover, score, report, outreach, follow-up.
        </p>
      </div>

      <div className="workflow-steps">
        {STEPS.map((step) => (
          <article key={step.n} className="workflow-step">
            <div className="workflow-step-n mono">{step.n}</div>
            <h3>{step.title}</h3>
            <p>{step.body}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

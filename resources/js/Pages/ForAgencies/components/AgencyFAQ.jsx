import { useState } from 'react';
import { Link } from '@inertiajs/react';

export default function AgencyFAQ() {
  const [open, setOpen] = useState(0);
  const items = [
    {
      q: 'Is this the same product as the free SME audit on your homepage?',
      a: 'Related, different audience. The homepage sells audit deliverables to business owners. Prospect Scanner is the operator tool behind those audits — prospect discovery, scoring, outreach, and pipeline tracking for agencies.',
    },
    {
      q: 'Can I use it today?',
      a: 'It is in active use at nthdesigns. External SaaS access is opening on a request basis while we finish billing and onboarding. Register interest and we will prioritise agencies in complementary niches.',
    },
    {
      q: 'How is this different from BrightLocal or Semrush?',
      a: 'Those tools audit or rank — they do not combine local prospect discovery, dual GBP + accessibility scoring, AI outreach with embedded report links, and warm-lead tracking in one workflow.',
    },
    {
      q: 'What does a prospect see?',
      a: 'An unlisted report URL with their grade, specific violations, screenshots, and GBP comparison against the top local competitor. No login. Optional booking CTA. Expires after 30 days by default.',
    },
    {
      q: 'Do you white-label the reports?',
      a: 'Planned on the £149/month tier — custom branding on public report pages. Available at launch for early partners.',
    },
    {
      q: 'What about MCP / AI agent access?',
      a: 'OAuth-connected agents (Cursor, Claude, ChatGPT) can monitor searches and trigger single-site audits. Included on the white-label tier; useful for technical agency operators.',
    },
  ];

  return (
    <section className="s" id="faq">
      <div className="section-head">
        <div>
          <span className="eyebrow">Questions</span>
          <h2 className="heading">Before you request access.</h2>
        </div>
        <p className="note">
          Email <span className="mono">hello@nthdesigns.co.uk</span> for a 20-minute walkthrough.
          Looking for a personal audit instead? <Link href="/">See the SME homepage →</Link>
        </p>
      </div>

      <div className="faq-list">
        {items.map((it, i) => (
          <div key={i} className={`faq-item ${open === i ? 'open' : ''}`}>
            <div className="faq-q" onClick={() => setOpen(open === i ? -1 : i)}>
              <span>{it.q}</span>
              <span className="toggle">+</span>
            </div>
            <div className="faq-a">{it.a}</div>
          </div>
        ))}
      </div>
    </section>
  );
}

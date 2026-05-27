import { useState } from 'react';

export default function FAQ() {
  const [open, setOpen] = useState(0);
  const items = [
    {
      q: "Why does a higher score mean a worse site?",
      a: "On the internal tool, the score represents weakness — higher means more sales potential for us. On your public report you only see an A–F grade and the count of issues. The internal jargon never reaches the prospect.",
    },
    {
      q: "How accurate is a 90-second audit?",
      a: "It's accurate for the things we measure automatically — WCAG criteria that can be tested programmatically, profile field completeness, Lighthouse-scored performance signals. Roughly 80% of the issues we'd find with a manual audit show up in the automatic one. The fix package adds the manual layer.",
    },
    {
      q: "What if my site is already accessible?",
      a: "About one site in twenty is. If yours is, the report is short and we tell you so. We don't manufacture concerns — if there's nothing to fix, there's no fix package to sell.",
    },
    {
      q: "Will you share my data?",
      a: "No. Audit results are stored against an unlisted token URL with a 30-day expiry, accessible only to you and us. We don't share with marketing partners. We don't have marketing partners.",
    },
    {
      q: "Can I run a scan on a competitor?",
      a: "Yes — there's nothing to stop you, and we won't ask why. The audit only inspects what their site already publishes to the public web.",
    },
    {
      q: "Who is nthdesigns?",
      a: "An independent design and accessibility consultancy based in the UK, working with three new clients a quarter. We've shipped accessibility work for retailers, dental groups, professional service firms, and one cooperative bank. We are deliberately small.",
    },
    {
      q: "How does pricing work for non-UK businesses?",
      a: "The audit is free for anyone. The fix package is the same price worldwide. Retainers are UK-only — the EHRC enforcement context is what makes the retainer worth the money, and that's a UK concern.",
    },
  ];
  return (
    <section className="s" id="faq">
      <div className="section-head">
        <div>
          <span className="eyebrow">Questions</span>
          <h2 className="heading">Anything else worth checking before booking.</h2>
        </div>
        <p className="note">
          Email <span className="mono">hello@nthdesigns.co.uk</span> if your question isn't here.
          Replies usually inside four hours during a UK working day.
        </p>
      </div>

      <div className="faq-list">
        {items.map((it, i) => (
          <div key={i} className={`faq-item ${open === i ? "open" : ""}`}>
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

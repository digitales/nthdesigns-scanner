import { Button, SevChip } from '@/Components/ui';
import Arrow from './Arrow';

export default function SampleReportExcerpt() {
  return (
    <section className="s full" id="sample">
      <div className="inner">
        <div className="section-head">
          <div>
            <span className="eyebrow">Sample report</span>
            <h2 className="heading">An excerpt from a real audit, lightly anonymised.</h2>
          </div>
          <p className="note">
            The report below is what the prospect sees when you forward them their token URL.
            No login, no internal jargon, no "combined score". An A–F grade, the top five
            violations, and one call-to-action.
          </p>
        </div>

        <div className="sample-report">
          <div className="sr-chrome">
            <span className="dot" />
            <span className="dot" />
            <span className="dot" />
            <span style={{ marginLeft: 12 }}>report.nthdesigns.co.uk/r/sample-bx7-9k2m</span>
          </div>
          <div className="sr-pad">
            <div className="sr-eyebrow">Independent audit · WCAG 2.2 + Google Business Profile</div>
            <h3>Birmingham Dental Practice</h3>
            <div className="sr-meta">birminghamdentalpractice.co.uk · audit 22 May 2026</div>

            <div className="sr-grade-row">
              <div className="grade">D</div>
              <p>
                We audited your website and Google Business Profile against the 2025 WCAG 2.2
                standard and the best-performing dental practice within 5 km of B5 6RG. The
                audit found <b>23 issues</b>, four of which are likely blocking patient
                bookings today.
              </p>
            </div>

            <div style={{ display: "flex", gap: 8, marginBottom: 36, flexWrap: "wrap" }}>
              <SevChip level="critical" count={4} />
              <SevChip level="serious" count={11} />
              <SevChip level="moderate" count={8} />
            </div>

            <div className="sr-violations">
              <SampleViol n={1} sev="critical" wcag="1.4.3 · Contrast" title="Header text contrast 2.1 : 1 fails WCAG AA"
                impact="On the homepage hero, the white-on-tan headline sits below 4.5:1. Patients with low vision will skip it entirely."
                fix="Darken the hero background to #5a4a30 or use #1f1410 text. One CSS line."
              />
              <SampleViol n={2} sev="critical" wcag="1.1.1 · Non-text content" title="42 images have no alternative text"
                impact="Screen readers announce these as &quot;image&quot; with no further context — including every staff portrait on the About page."
                fix="Add an alt attribute to each <img>. &quot;Dr Sarah Kavanagh, lead dentist&quot; beats both empty and &quot;image of dentist&quot;."
              />
            </div>

            <div style={{ textAlign: "center", paddingTop: 36, marginTop: 36, borderTop: "1px solid var(--line)" }}>
              <Button kind="secondary">Read the full sample report <Arrow /></Button>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function SampleViol({ n, sev, wcag, title, impact, fix }) {
  return (
    <article className="sr-viol">
      <div className="ss">
        <div className="ss-bar" style={{ top: 10, left: 10, right: 10, height: 32 }} />
        <div className="ss-tag" style={{ top: -1, right: 8 }}>{sev === "critical" ? "2.1:1" : "issue"}</div>
        <div style={{ height: 40, background: "oklch(0.78 0.04 70)", borderRadius: 2, padding: 6, marginTop: 4 }}>
          <div style={{ color: "oklch(0.88 0.02 70)", fontFamily: "var(--serif)", fontSize: 10, lineHeight: 1.2 }}>
            Modern dentistry in<br />the heart of Birmingham
          </div>
        </div>
        <div style={{ marginTop: 6, display: "flex", gap: 4 }}>
          <div style={{ flex: 1, height: 4, background: "var(--stone-200)", borderRadius: 1 }} />
          <div style={{ flex: 1, height: 4, background: "var(--stone-200)", borderRadius: 1 }} />
        </div>
      </div>
      <div>
        <div className="header">
          <SevChip level={sev} />
          <span className="mono" style={{ fontSize: 10.5, color: "var(--stone-500)", letterSpacing: "0.06em", textTransform: "uppercase" }}>{wcag}</span>
        </div>
        <h4>{n}. {title}</h4>
        <p>{impact}</p>
        <div className="fix">
          <span className="fixlabel">Fix</span>{fix}
        </div>
      </div>
    </article>
  );
}

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
            No login, no internal jargon — a plain-English summary, the top violations, and one call-to-action.
          </p>
        </div>

        <div className="sample-report">
          <div className="sr-chrome">
            <span className="dot" />
            <span className="dot" />
            <span className="dot" />
            <span className="sr-url">report.nthdesigns.co.uk/r/sample-bx7-9k2m</span>
          </div>
          <div className="sr-pad">
            <div className="sr-eyebrow">Independent audit · WCAG 2.2 + Google Business Profile</div>
            <h3>Birmingham Dental Practice</h3>
            <div className="sr-meta">birminghamdentalpractice.co.uk · audit 22 May 2026</div>

            <div className="sr-key-finding">
              <span className="sr-kf-label">Key finding</span>
              <p>
                Your site has barriers that can stop patients from booking online — while your Google profile trails the top dental practice in Birmingham on reviews (12 vs 89).
              </p>
            </div>

            <div className="sr-grade-row">
              <div className="grade">D</div>
              <p>
                We audited your website and Google Business Profile against WCAG 2.2 and local competitors in Birmingham.
                The audit found <b>23 issues</b> worth addressing.
              </p>
            </div>

            <div className="sr-sev-chips">
              <SevChip level="critical" label="4 likely blocking enquiries" />
              <SevChip level="serious" label="11 serious" />
              <SevChip level="moderate" label="8 moderate" />
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

            <div className="sr-cta-row">
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
        <div className="ss-bar demo" />
        <div className="ss-tag demo">{sev === "critical" ? "2.1:1" : "issue"}</div>
        <div className="sr-viol-demo">
          <div className="sr-viol-demo-text">
            Modern dentistry in<br />the heart of Birmingham
          </div>
        </div>
        <div className="sr-viol-skeleton">
          <span />
          <span />
        </div>
      </div>
      <div>
        <div className="header">
          <SevChip level={sev} />
          <span className="mono sr-viol-wcag">{wcag}</span>
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

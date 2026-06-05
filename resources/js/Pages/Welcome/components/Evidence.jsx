import { SevChip } from '@/Components/ui';

export default function Evidence() {
  return (
    <section className="s full" id="evidence">
      <div className="inner">
        <div className="section-head">
          <div>
            <span className="eyebrow">Evidence</span>
            <h2 className="heading">What a fix looks like, side by side.</h2>
          </div>
          <p className="note">
            These are the most common issues we flag. Each takes between five minutes and
            half a day to fix. None require a full redesign.
          </p>
        </div>

        <div className="evidence">
          <EvCard
            sev="critical"
            wcag="1.4.3 · Contrast"
            title="Header text contrast"
            beforeRender={
              <div className="ev-contrast-pane before">
                <div className="ev-contrast-text before">
                  Modern dentistry in<br />the heart of Birmingham
                </div>
              </div>
            }
            afterRender={
              <div className="ev-contrast-pane after">
                <div className="ev-contrast-text after">
                  Modern dentistry in<br />the heart of Birmingham
                </div>
              </div>
            }
            beforeMeta="2.1 : 1 ratio · fails AA"
            afterMeta="8.4 : 1 · passes AAA"
          />

          <EvCard
            sev="critical"
            wcag="1.1.1 · Non-text content"
            title="Image alt text"
            beforeRender={
              <div className="ev-alt-grid">
                {Array.from({ length: 4 }).map((_, i) => (
                  <div key={i} className="ev-alt-cell">
                    <span className="mono ev-alt-empty">alt=""</span>
                  </div>
                ))}
              </div>
            }
            afterRender={
              <div className="ev-alt-grid">
                {["Dr Sarah Kavanagh", "Treatment room 2", "Reception", "Surgery exterior"].map((t, i) => (
                  <div key={i} className="ev-alt-cell labeled">
                    <span className="mono ev-alt-caption">{`alt="${t}"`}</span>
                  </div>
                ))}
              </div>
            }
            beforeMeta="42 images, no alt text"
            afterMeta="all 42 described"
          />

          <EvCard
            sev="serious"
            wcag="1.3.1 · Info and Relationships"
            title="Booking form labels"
            beforeRender={
              <div className="ev-form-wrap">
                {["Your name", "Email address", "Preferred date"].map((p, i) => (
                  <div key={i} className="ev-form-row">
                    <span className="ev-form-placeholder">{p}</span>
                  </div>
                ))}
              </div>
            }
            afterRender={
              <div className="ev-form-wrap">
                {["Your name", "Email address", "Preferred date"].map((p, i) => (
                  <div key={i} className="ev-form-row labeled">
                    <div className="ev-form-label">{p}</div>
                    <div className="ev-form-input" />
                  </div>
                ))}
              </div>
            }
            beforeMeta="placeholders only"
            afterMeta="<label> attached"
          />

          <EvCard
            sev="moderate"
            wcag="GBP visibility"
            title="Google profile photos"
            beforeRender={
              <div>
                <div className="mono ev-gbp-meta before">Your profile · 3 photos</div>
                <div className="ev-gbp-grid-3">
                  {Array.from({ length: 3 }).map((_, i) => (
                    <div key={i} className="ev-gbp-photo" />
                  ))}
                </div>
              </div>
            }
            afterRender={
              <div>
                <div className="mono ev-gbp-meta after">Top competitor · 42 photos</div>
                <div className="ev-gbp-grid-6">
                  {Array.from({ length: 18 }).map((_, i) => (
                    <div key={i} className="ev-gbp-photo alt" />
                  ))}
                </div>
              </div>
            }
            beforeMeta="ranked 7th locally"
            afterMeta="ranked 1st"
          />
        </div>
      </div>
    </section>
  );
}

function EvCard({ sev, wcag, title, beforeRender, afterRender, beforeMeta, afterMeta }) {
  return (
    <div className="ev-card">
      <div className="ev-head">
        <div className="eyebrow no-rule ev-head-eyebrow">
          <span>{wcag}</span>
          <SevChip level={sev} />
        </div>
        <h3>{title}</h3>
      </div>
      <div className="ev-body">
        <div className="ev-pane before">
          <div className="pane-label">Before · what we audit</div>
          {beforeRender}
          <div className="mono ev-meta-before">{beforeMeta}</div>
        </div>
        <div className="ev-pane after">
          <div className="pane-label">After · what to ship</div>
          {afterRender}
          <div className="mono ev-meta-after">{afterMeta}</div>
        </div>
      </div>
    </div>
  );
}

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
              <div style={{ height: 100, background: "oklch(0.78 0.04 70)", borderRadius: 4, padding: 16, display: "flex", alignItems: "center" }}>
                <div style={{ color: "oklch(0.88 0.02 70)", fontFamily: "var(--serif)", fontSize: 18, lineHeight: 1.15 }}>
                  Modern dentistry in<br />the heart of Birmingham
                </div>
              </div>
            }
            afterRender={
              <div style={{ height: 100, background: "oklch(0.32 0.04 50)", borderRadius: 4, padding: 16, display: "flex", alignItems: "center" }}>
                <div style={{ color: "oklch(0.97 0.02 70)", fontFamily: "var(--serif)", fontSize: 18, lineHeight: 1.15 }}>
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
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 4 }}>
                {Array.from({ length: 4 }).map((_, i) => (
                  <div key={i} style={{ aspectRatio: "4/3", background: "var(--stone-200)", borderRadius: 2, position: "relative", display: "flex", alignItems: "center", justifyContent: "center" }}>
                    <span className="mono" style={{ fontSize: 10, color: "var(--sev-critical)" }}>alt=""</span>
                  </div>
                ))}
              </div>
            }
            afterRender={
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 4 }}>
                {["Dr Sarah Kavanagh", "Treatment room 2", "Reception", "Surgery exterior"].map((t, i) => (
                  <div key={i} style={{ aspectRatio: "4/3", background: "var(--stone-200)", borderRadius: 2, position: "relative", display: "flex", alignItems: "flex-end", padding: 6 }}>
                    <span className="mono" style={{ fontSize: 9, color: "var(--positive)", background: "var(--paper)", padding: "2px 4px", borderRadius: 1 }}>{`alt="${t}"`}</span>
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
              <div style={{ background: "var(--paper-2)", padding: 12, borderRadius: 3 }}>
                {["Your name", "Email address", "Preferred date"].map((p, i) => (
                  <div key={i} style={{ height: 22, background: "var(--paper)", border: "1px solid var(--line-strong)", borderRadius: 2, marginBottom: 6, padding: "0 8px", display: "flex", alignItems: "center" }}>
                    <span style={{ fontSize: 11, color: "var(--stone-400)", fontStyle: "italic" }}>{p}</span>
                  </div>
                ))}
              </div>
            }
            afterRender={
              <div style={{ background: "var(--paper-2)", padding: 12, borderRadius: 3 }}>
                {["Your name", "Email address", "Preferred date"].map((p, i) => (
                  <div key={i} style={{ marginBottom: 8 }}>
                    <div style={{ fontSize: 10, color: "var(--stone-700)", fontWeight: 500, marginBottom: 2 }}>{p}</div>
                    <div style={{ height: 18, background: "var(--paper)", border: "1px solid var(--line-strong)", borderRadius: 2 }} />
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
                <div className="mono" style={{ fontSize: 11, color: "var(--stone-600)", marginBottom: 10 }}>Your profile · 3 photos</div>
                <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 4 }}>
                  {Array.from({ length: 3 }).map((_, i) => (
                    <div key={i} style={{ aspectRatio: "1/1", background: "var(--stone-200)", borderRadius: 2 }} />
                  ))}
                </div>
              </div>
            }
            afterRender={
              <div>
                <div className="mono" style={{ fontSize: 11, color: "var(--positive)", marginBottom: 10 }}>Top competitor · 42 photos</div>
                <div style={{ display: "grid", gridTemplateColumns: "repeat(6, 1fr)", gap: 3 }}>
                  {Array.from({ length: 18 }).map((_, i) => (
                    <div key={i} style={{ aspectRatio: "1/1", background: i % 2 === 0 ? "oklch(0.78 0.04 70)" : "var(--stone-300)", borderRadius: 1 }} />
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
        <div className="eyebrow no-rule" style={{ display: "flex", justifyContent: "space-between", width: "100%" }}>
          <span>{wcag}</span>
          <SevChip level={sev} />
        </div>
        <h3>{title}</h3>
      </div>
      <div className="ev-body">
        <div className="ev-pane before">
          <div className="pane-label">Before · what we audit</div>
          {beforeRender}
          <div className="mono" style={{ fontSize: 11, color: "var(--sev-critical)", marginTop: 12, letterSpacing: 0.04 }}>{beforeMeta}</div>
        </div>
        <div className="ev-pane after">
          <div className="pane-label">After · what to ship</div>
          {afterRender}
          <div className="mono" style={{ fontSize: 11, color: "var(--positive)", marginTop: 12, letterSpacing: 0.04 }}>{afterMeta}</div>
        </div>
      </div>
    </div>
  );
}

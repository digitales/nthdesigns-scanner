import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/Components/ui';
import Arrow from './Arrow';

export default function AuditWidget({ kind = "primary", autoStart = false }) {
  const [url, setUrl] = useState("");
  const [phase, setPhase] = useState(autoStart ? "running" : "idle");
  const [tick, setTick] = useState(0);

  useEffect(() => {
    if (autoStart) {
      setUrl("birminghamdentalpractice.co.uk");
      const t = setTimeout(() => setPhase("complete"), 1400);
      return () => clearTimeout(t);
    }
  }, [autoStart]);

  useEffect(() => {
    if (phase !== "running") return;
    const id = setInterval(() => setTick(t => t + 1), 80);
    return () => clearInterval(id);
  }, [phase]);

  const run = () => {
    if (!url.trim()) return;
    setPhase("running");
    setTimeout(() => setPhase("complete"), 1900);
  };

  // Demo scores derived deterministically from URL hash
  const result = useMemo(() => {
    const h = [...(url || "demo")].reduce((a, c) => (a * 31 + c.charCodeAt(0)) >>> 0, 7);
    const combined = 55 + (h % 40);
    const a11y = 50 + ((h >> 3) % 45);
    const gbp = 45 + ((h >> 6) % 45);
    const perf = 18 + ((h >> 9) % 45);
    const grade = combined >= 85 ? "D" : combined >= 70 ? "C" : combined >= 50 ? "C+" : "B";
    return { combined, a11y, gbp, perf, grade };
  }, [url]);

  return (
    <div className="audit-widget">
      <div className="widget-label">Audit your site in 90 seconds</div>
      <div className="audit-row">
        <div className="audit-input">
          <span className="prefix">https://</span>
          <input
            placeholder="your-business.co.uk"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            onKeyDown={(e) => e.key === "Enter" && run()}
            disabled={phase === "running"}
          />
        </div>
        <Button kind={kind === "accent" ? "accent" : "primary"} size="lg" onClick={run} disabled={phase === "running"}>
          {phase === "running"
            ? <><span className="spinner" /> Running audit</>
            : phase === "complete"
              ? <>Run another <Arrow /></>
              : <>Run audit <Arrow /></>}
        </Button>
      </div>
      <div className="hint">
        We'll check WCAG 2.2 compliance and Google Business Profile health. No login. No email required to see the result.
      </div>

      {phase === "running" && (
        <div className="fade-in" style={{ marginTop: 22, paddingTop: 22, borderTop: "1px solid var(--line)" }}>
          <div className="mono" style={{ fontSize: 12, color: "var(--stone-600)", lineHeight: 1.7 }}>
            <div>{tick > 0  ? "✓" : "·"} Discovered profile on Google Places</div>
            <div>{tick > 3  ? "✓" : "·"} Fetched 12 page sample from your site</div>
            <div>{tick > 6  ? "✓" : "·"} Running axe-core + Lighthouse</div>
            <div>{tick > 10 ? "✓" : "·"} Comparing GBP to top 3 competitors</div>
            <div>{tick > 14 ? "✓" : "·"} Compiling report</div>
          </div>
        </div>
      )}

      {phase === "complete" && (
        <div className="fade-in" style={{ marginTop: 22, paddingTop: 22, borderTop: "1px solid var(--line)" }}>
          <CompleteAuditCard result={result} url={url || "your-business.co.uk"} />
        </div>
      )}
    </div>
  );
}

function CompleteAuditCard({ result, url }) {
  const gradeColor = result.combined >= 85 ? "var(--sev-critical)" : result.combined >= 70 ? "oklch(0.55 0.14 50)" : "var(--positive)";
  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 18, gap: 14, flexWrap: "wrap" }}>
        <div>
          <div className="mono" style={{ fontSize: 11, color: "var(--stone-500)", letterSpacing: "0.08em", textTransform: "uppercase", marginBottom: 4 }}>{url}</div>
          <div style={{ fontFamily: "var(--serif)", fontSize: 22, letterSpacing: "-0.01em" }}>
            Grade <em style={{ fontStyle: "italic", color: gradeColor, fontWeight: 500 }}>{result.grade}</em> · {result.combined}/100 opportunity
          </div>
        </div>
        <Button kind="accent" size="sm">View full report <Arrow /></Button>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 8 }}>
        <MiniScore label="A11y" value={result.a11y} kind={result.a11y < 50 ? "crit" : result.a11y > 70 ? "warm" : ""} />
        <MiniScore label="GBP" value={result.gbp} kind={result.gbp < 50 ? "crit" : result.gbp > 70 ? "warm" : ""} />
        <MiniScore label="Perf" value={result.perf} kind={result.perf < 30 ? "crit" : ""} />
        <MiniScore label="Issues" value={Math.round(result.combined / 7)} kind={result.combined > 80 ? "crit" : ""} suffix=" found" />
      </div>
      <div className="mono" style={{ fontSize: 11, color: "var(--stone-500)", marginTop: 16, letterSpacing: "0.02em" }}>
        Live demo · numbers generated from a deterministic hash. Real audits take ~90 seconds and run server-side.
      </div>
    </div>
  );
}

function MiniScore({ label, value, kind, suffix }) {
  return (
    <div className="mini-score" style={kind === "warm" ? { background: "var(--accent-soft)", borderColor: "oklch(0.85 0.08 65)" } : kind === "crit" ? { background: "var(--sev-critical-soft)", borderColor: "oklch(0.515 0.180 28 / 0.18)" } : {}}>
      <div className="ms-label">{label}</div>
      <div className={`ms-value ${kind || ""}`}>{value}{suffix || ""}</div>
    </div>
  );
}

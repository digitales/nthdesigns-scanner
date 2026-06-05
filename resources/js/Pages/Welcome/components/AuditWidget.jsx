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
        <div className="fade-in audit-phase-panel">
          <div className="mono audit-progress-mono">
            <div>{tick > 0  ? "✓" : "·"} Discovered profile on Google Places</div>
            <div>{tick > 3  ? "✓" : "·"} Fetched 12 page sample from your site</div>
            <div>{tick > 6  ? "✓" : "·"} Running axe-core + Lighthouse</div>
            <div>{tick > 10 ? "✓" : "·"} Comparing GBP to top 3 competitors</div>
            <div>{tick > 14 ? "✓" : "·"} Compiling report</div>
          </div>
        </div>
      )}

      {phase === "complete" && (
        <div className="fade-in audit-phase-panel">
          <CompleteAuditCard result={result} url={url || "your-business.co.uk"} />
        </div>
      )}
    </div>
  );
}

function CompleteAuditCard({ result, url }) {
  const gradeClass = result.combined >= 85 ? "grade-critical" : result.combined >= 70 ? "grade-warm" : "grade-positive";

  return (
    <div>
      <div className="audit-complete-header">
        <div>
          <div className="mono audit-complete-url">{url}</div>
          <div className="audit-complete-grade">
            Grade <em className={gradeClass}>{result.grade}</em> · {result.combined}/100 opportunity
          </div>
        </div>
        <Button kind="accent" size="sm">View full report <Arrow /></Button>
      </div>
      <div className="audit-complete-scores">
        <MiniScore label="A11y" value={result.a11y} kind={result.a11y < 50 ? "crit" : result.a11y > 70 ? "warm" : ""} />
        <MiniScore label="GBP" value={result.gbp} kind={result.gbp < 50 ? "crit" : result.gbp > 70 ? "warm" : ""} />
        <MiniScore label="Perf" value={result.perf} kind={result.perf < 30 ? "crit" : ""} />
        <MiniScore label="Issues" value={Math.round(result.combined / 7)} kind={result.combined > 80 ? "crit" : ""} suffix=" found" />
      </div>
      <div className="mono audit-complete-footnote">
        Live demo · numbers generated from a deterministic hash. Real audits take ~90 seconds and run server-side.
      </div>
    </div>
  );
}

function MiniScore({ label, value, kind, suffix }) {
  return (
    <div className={`mini-score${kind ? ` ${kind}` : ''}`}>
      <div className="ms-label">{label}</div>
      <div className={`ms-value ${kind || ""}`}>{value}{suffix || ""}</div>
    </div>
  );
}

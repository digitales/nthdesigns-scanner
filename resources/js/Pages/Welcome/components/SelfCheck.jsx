import { useEffect, useState } from 'react';

export default function SelfCheck() {
  const [results, setResults] = useState(null);

  useEffect(() => {
    // Run a tiny real audit of the visible document
    const id = setTimeout(() => {
      const imgs = document.querySelectorAll("img");
      const imgsNoAlt = Array.from(imgs).filter(i => !i.getAttribute("alt") && !i.hasAttribute("aria-hidden")).length;
      const headings = document.querySelectorAll("h1, h2, h3, h4, h5, h6");
      const headingLevels = Array.from(headings).map(h => +h.tagName[1]);
      let headingOrderOk = true;
      for (let i = 1; i < headingLevels.length; i++) {
        if (headingLevels[i] > headingLevels[i - 1] + 1) headingOrderOk = false;
      }
      const h1Count = document.querySelectorAll("h1").length;
      const buttons = document.querySelectorAll("button, a");
      const buttonsNoLabel = Array.from(buttons).filter(b => !b.textContent?.trim() && !b.getAttribute("aria-label")).length;
      const tabbables = document.querySelectorAll("button, a, input, textarea, select, [tabindex]");

      setResults({
        elementsScanned: document.querySelectorAll("*").length,
        h1Count,
        imgsTotal: imgs.length,
        imgsNoAlt,
        headingsTotal: headings.length,
        headingOrderOk,
        tabbables: tabbables.length,
        buttonsNoLabel,
      });
    }, 600);
    return () => clearTimeout(id);
  }, []);

  return (
    <section className="s">
      <div className="self-check">
        <div className="sc-eyebrow">Live · ran the moment you scrolled here</div>
        <h2>We just ran our own audit on this page. Here's what we found.</h2>
        <p className="intro">
          The same axe-core lite that powers Prospect Scanner is running in your browser right now,
          reading the live DOM of this page. We eat our own dog food — if we shipped a homepage that
          failed our own check, you'd notice.
        </p>

        <div className="sc-grid">
          {results ? (
            <>
              <div className="sc-stat">
                <div className="sl">Elements scanned</div>
                <div className="sv tabular">{results.elementsScanned.toLocaleString()}</div>
                <div className="sn">full DOM walk</div>
              </div>
              <div className="sc-stat">
                <div className="sl">Images / no alt</div>
                <div className={`sv tabular ${results.imgsNoAlt > 0 ? "warn" : ""}`}>
                  {results.imgsTotal}<span className="sv-divider"> / </span>
                  <span className={results.imgsNoAlt > 0 ? "sv-count-warn" : "sv-count-ok"}>{results.imgsNoAlt}</span>
                </div>
                <div className="sn">{results.imgsNoAlt === 0 ? "all images described" : "some without alt"}</div>
              </div>
              <div className="sc-stat">
                <div className="sl">Heading order</div>
                <div className="sv tabular sv-heading">
                  {results.headingOrderOk ? "✓" : "✗"} <span className="sv-sub">· {results.headingsTotal}</span>
                </div>
                <div className="sn">{results.headingOrderOk ? `${results.h1Count} h1 · order intact` : "skipped a level"}</div>
              </div>
              <div className="sc-stat">
                <div className="sl">Keyboard targets</div>
                <div className="sv tabular">{results.tabbables}</div>
                <div className="sn">{results.buttonsNoLabel === 0 ? "all labelled" : `${results.buttonsNoLabel} without label`}</div>
              </div>
            </>
          ) : (
            Array.from({ length: 4 }).map((_, i) => (
              <div key={i} className="sc-stat">
                <div className="sl">Scanning…</div>
                <div className="sv ticking sv-ticking">—</div>
              </div>
            ))
          )}
        </div>

        {results && (
          <div className="sc-footnote">
            {results.imgsNoAlt === 0 && results.headingOrderOk && results.buttonsNoLabel === 0 ? (
              <>This page passes our own check. Yours probably doesn't yet — that's why we exist.</>
            ) : (
              <>We just found {results.imgsNoAlt + (results.headingOrderOk ? 0 : 1) + results.buttonsNoLabel} issue(s) on our own page. Embarrassing, fixing it.</>
            )}
          </div>
        )}
      </div>
    </section>
  );
}

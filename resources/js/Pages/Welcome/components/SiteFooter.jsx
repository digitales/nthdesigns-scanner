export default function SiteFooter({ scrollTo }) {
  return (
    <footer className="site">
      <div className="inner">
        <div className="pillar">
          <div className="nav-brand footer-brand">
            <span className="brand-mark" />
            <span className="brand-name">nthdesigns</span>
          </div>
          <p>
            An independent design and accessibility consultancy in the UK.<br />
            We work with three new clients a quarter. We don't take retainers under £900/mo.
          </p>
          <p className="mono footer-location">
            Birmingham · Manchester · remote across the UK
          </p>
        </div>
        <div>
          <h5>Product</h5>
          <div className="links">
            <a>How it works</a>
            <a>Sample report</a>
            <a>Pricing</a>
            <a>Methodology</a>
            <a>Changelog</a>
          </div>
        </div>
        <div>
          <h5>Resources</h5>
          <div className="links">
            <a>WCAG 2.2 explained</a>
            <a>EHRC guidance summary</a>
            <a>Audit checklist (PDF)</a>
            <a>For agencies (white-label)</a>
          </div>
        </div>
        <div>
          <h5>Company</h5>
          <div className="links">
            <a>About</a>
            <a>Past work</a>
            <a>hello@nthdesigns.co.uk</a>
            <a>Privacy &amp; data</a>
          </div>
        </div>
      </div>
      <div className="copy">
        <span>© 2026 nthdesigns Ltd · Company 12 884 901</span>
        <span>Registered in England and Wales · Audits delivered worldwide</span>
      </div>
    </footer>
  );
}

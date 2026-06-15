import { Link } from '@inertiajs/react';

export default function SiteFooter({ scrollTo, marketingPath = '/' }) {
  const productLinks = [
    { label: 'How it works', href: marketingPath === '/' ? '#how' : '/#how' },
    { label: 'Sample report', href: marketingPath === '/' ? '#sample' : '/#sample' },
    { label: 'Pricing', href: marketingPath === '/' ? '#pricing' : '/#pricing' },
    { label: 'For agencies', href: route('marketing.for-agencies') },
  ];

  const resourceLinks = [
    { label: 'SME audits', href: '/' },
    { label: 'Prospect Scanner', href: route('marketing.for-agencies') },
    { label: 'hello@nthdesigns.co.uk', href: 'mailto:hello@nthdesigns.co.uk' },
  ];

  return (
    <footer className="site">
      <div className="inner">
        <div className="pillar">
          <Link href="/" className="nav-brand footer-brand">
            <span className="brand-mark" />
            <span className="brand-name">nthdesigns</span>
          </Link>
          <p>
            An independent design and accessibility consultancy in the UK.<br />
            We work with three new clients a quarter. We don&apos;t take retainers under £900/mo.
          </p>
          <p className="mono footer-location">
            Birmingham · Manchester · remote across the UK
          </p>
        </div>
        <div>
          <h5>Product</h5>
          <div className="links">
            {productLinks.map((l) => (
              <Link key={l.label} href={l.href}>{l.label}</Link>
            ))}
          </div>
        </div>
        <div>
          <h5>Resources</h5>
          <div className="links">
            {resourceLinks.map((l) => (
              l.href.startsWith('mailto:') ? (
                <a key={l.label} href={l.href}>{l.label}</a>
              ) : (
                <Link key={l.label} href={l.href}>{l.label}</Link>
              )
            ))}
          </div>
        </div>
        <div>
          <h5>Company</h5>
          <div className="links">
            <a href="mailto:hello@nthdesigns.co.uk">hello@nthdesigns.co.uk</a>
            <Link href="/">Free SME audit</Link>
            <Link href={route('marketing.for-agencies')}>Agency early access</Link>
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

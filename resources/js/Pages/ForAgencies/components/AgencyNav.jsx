import { Link } from '@inertiajs/react';
import { Brand, Button, LinkButton } from '@/Components/ui';
import Arrow from '../../Welcome/components/Arrow';

export default function AgencyNav({ scrollTo, canLogin, canRegister }) {
  const jump = (id) => (e) => {
    e.preventDefault();
    scrollTo(id);
  };

  return (
    <nav className="site-nav">
      <Brand href="/for-agencies" className="nav-brand" />
      <div className="nav-links">
        <button type="button" onClick={jump('workflow')}>Workflow</button>
        <button type="button" onClick={jump('features')}>Features</button>
        <button type="button" onClick={jump('compare')}>vs SEO tools</button>
        <button type="button" onClick={jump('pricing')}>Pricing</button>
        <button type="button" onClick={jump('faq')}>FAQ</button>
        <Link href="/" className="nav-text-link">SME audits</Link>
      </div>
      <div className="nav-cta">
        {canLogin ? (
          <Link href={route('login')} className="secondary nav-sign-in">
            Sign in
          </Link>
        ) : null}
        {canRegister ? (
          <LinkButton href={route('register')} kind="ghost" size="sm">
            Register
          </LinkButton>
        ) : null}
        <Button type="button" kind="primary" size="sm" onClick={() => scrollTo('cta')}>
          Request access <Arrow />
        </Button>
      </div>
    </nav>
  );
}

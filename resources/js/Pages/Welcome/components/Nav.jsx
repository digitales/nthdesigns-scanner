import { Link } from '@inertiajs/react';
import { Brand, Button, LinkButton } from '@/Components/ui';
import Arrow from './Arrow';

export default function Nav({ scrollTo, canLogin, canRegister }) {
  const jump = (id) => (e) => {
    e.preventDefault();
    scrollTo(id);
  };

  return (
    <nav className="site-nav">
      <Brand href="/" className="nav-brand" />
      <div className="nav-links">
        <button type="button" onClick={jump('how')}>How it works</button>
        <button type="button" onClick={jump('sample')}>Sample report</button>
        <button type="button" onClick={jump('evidence')}>Evidence</button>
        <button type="button" onClick={jump('compare')}>vs SEO tools</button>
        <button type="button" onClick={jump('pricing')}>Pricing</button>
        <button type="button" onClick={jump('faq')}>FAQ</button>
      </div>
      <div className="nav-cta">
        {canLogin ? (
          <Link href={route('login')} className="secondary" style={{ fontSize: 13, color: 'var(--stone-700)', padding: '6px 10px' }}>
            Sign in
          </Link>
        ) : null}
        {canRegister ? (
          <LinkButton href={route('register')} kind="ghost" size="sm">
            Register
          </LinkButton>
        ) : null}
        <Button type="button" kind="primary" size="sm" onClick={() => scrollTo('hero')}>
          Get your audit <Arrow />
        </Button>
      </div>
    </nav>
  );
}

import { Brand } from '@/Components/ui';
import { Head } from '@inertiajs/react';

export default function AuthLayout({ title, children }) {
    return (
        <>
            {title ? <Head title={title} /> : null}
            <div className="auth-shell">
                <div className="auth-shell-card">
                    <div className="auth-shell-brand">
                        <Brand href="/" />
                    </div>
                    {children}
                </div>
            </div>
        </>
    );
}

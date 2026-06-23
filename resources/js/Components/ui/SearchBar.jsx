import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import Input from './Input';

export default function SearchBar() {
    const { url } = usePage();

    const queryFromUrl = useMemo(() => {
        try {
            return new URL(url, window.location.origin).searchParams.get('q') ?? '';
        } catch {
            return '';
        }
    }, [url]);

    const [q, setQ] = useState(queryFromUrl);

    useEffect(() => {
        setQ(queryFromUrl);
    }, [queryFromUrl]);

    const submit = (e) => {
        e.preventDefault();
        router.get('/find', { q: q.trim() });
    };

    return (
        <form className="app-search" onSubmit={submit}>
            <Input
                type="search"
                name="q"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Search prospects, scans, lists…"
                aria-label="Site search"
            />
        </form>
    );
}

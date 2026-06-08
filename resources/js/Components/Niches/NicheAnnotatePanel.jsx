import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import TagInput from '@/Components/TagInput';
import { Button, Stack } from '@/Components/ui';

export default function NicheAnnotatePanel({ scan, onClose }) {
    const [tab, setTab] = useState('market');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [noteBody, setNoteBody] = useState('');

    const load = useCallback(async () => {
        setLoading(true);
        const params = new URLSearchParams({ niche_label: scan.niche, city: scan.city });
        const res = await fetch(`/niches/annotations?${params}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const json = await res.json();
        setData(json);
        setLoading(false);
    }, [scan.niche, scan.city]);

    useEffect(() => {
        load();
    }, [load]);

    const scope = tab === 'global' ? data?.global : data?.market;
    const cityParam = tab === 'global' ? null : scan.city;

    const addNote = (e) => {
        e.preventDefault();
        if (!noteBody.trim()) return;
        router.post('/niche-notes', {
            niche_label: scan.niche,
            city: cityParam,
            body: noteBody,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNoteBody('');
                load();
            },
        });
    };

    const syncTag = (action, tagName) => {
        router.post('/niche-tags', {
            niche_label: scan.niche,
            city: cityParam,
            action,
            tag_name: tagName,
        }, {
            preserveScroll: true,
            onSuccess: () => load(),
        });
    };

    return (
        <aside className="niches-panel" aria-label="Niche annotations">
            <div className="niches-panel-header">
                <div>
                    <div className="niches-panel-title">{scan.niche}</div>
                    <div className="micro niches-panel-meta">Annotations</div>
                </div>
                <button type="button" className="btn-ghost btn-xs" onClick={onClose} aria-label="Close panel">×</button>
            </div>

            <Stack direction="row" gap={8} className="niches-panel-tabs">
                <Button kind={tab === 'global' ? 'primary' : 'ghost'} size="xs" onClick={() => setTab('global')}>
                    Global
                </Button>
                <Button kind={tab === 'market' ? 'primary' : 'ghost'} size="xs" onClick={() => setTab('market')}>
                    {scan.city}
                </Button>
            </Stack>

            {loading ? (
                <p className="micro p-16">Loading…</p>
            ) : (
                <div className="niches-panel-body">
                    <TagInput
                        tags={scope?.tags ?? []}
                        suggestions={data?.tag_suggestions ?? []}
                        onAttach={(name) => syncTag('attach', name)}
                        onDetach={(name) => syncTag('detach', name)}
                    />

                    <Stack as="ul" gap={12} className="meta-list meta-list--notes mt-16">
                        {(scope?.notes ?? []).map((n) => (
                            <li key={n.id} className="note-item">
                                <p className="note-body">{n.body}</p>
                                <span className="micro">{n.created_at}</span>
                            </li>
                        ))}
                    </Stack>

                    <form onSubmit={addNote} className="mt-16">
                        <textarea
                            className="textarea w-full"
                            rows={3}
                            value={noteBody}
                            onChange={(e) => setNoteBody(e.target.value)}
                            placeholder="Add a note…"
                        />
                        <Button kind="primary" size="sm" type="submit" className="mt-8">
                            Add note
                        </Button>
                    </form>

                    {tab === 'market' && data?.market?.related_search_count > 0 && (
                        <p className="micro mt-16">
                            {data.market.related_search_count} related search
                            {data.market.related_search_count !== 1 ? 'es' : ''} for this market.
                        </p>
                    )}
                </div>
            )}
        </aside>
    );
}

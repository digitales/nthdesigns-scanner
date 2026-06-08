import { useState } from 'react';

export default function TagInput({ tags = [], suggestions = [], onAttach, onDetach, className = '' }) {
    const [value, setValue] = useState('');
    const tagNames = new Set(tags.map((t) => t.name ?? t));

    const submit = (name) => {
        const trimmed = name.trim();
        if (!trimmed) return;
        if (tagNames.has(trimmed)) return;
        onAttach(trimmed);
        setValue('');
    };

    const filteredSuggestions = suggestions.filter(
        (s) => !tagNames.has(s) && s.toLowerCase().includes(value.toLowerCase()),
    );

    return (
        <div className={className}>
            <div className="tag-chips">
                {tags.map((tag) => {
                    const name = tag.name ?? tag;
                    return (
                        <button
                            key={name}
                            type="button"
                            className="tag-chip"
                            onClick={() => onDetach(name)}
                            title="Remove tag"
                        >
                            {name} ×
                        </button>
                    );
                })}
            </div>
            <input
                type="text"
                className="input mt-8"
                placeholder="Add tag…"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submit(value);
                    }
                }}
                list="tag-suggestions"
            />
            {filteredSuggestions.length > 0 && value && (
                <ul className="tag-suggestions mt-4">
                    {filteredSuggestions.slice(0, 6).map((s) => (
                        <li key={s}>
                            <button type="button" className="btn-ghost btn-xs" onClick={() => submit(s)}>
                                {s}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

import { useId, useState } from 'react';

export default function TagInput({ tags = [], suggestions = [], onAttach, onDetach, className = '' }) {
    const [value, setValue] = useState('');
    const listboxId = useId();
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
    const visibleSuggestions = filteredSuggestions.slice(0, 6);
    const showSuggestions = visibleSuggestions.length > 0 && value.trim() !== '';

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
                role="combobox"
                aria-expanded={showSuggestions}
                aria-controls={showSuggestions ? listboxId : undefined}
                aria-autocomplete="list"
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (showSuggestions) {
                            submit(visibleSuggestions[0]);
                        } else {
                            submit(value);
                        }
                    }
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        setValue('');
                    }
                }}
            />
            {showSuggestions && (
                <div className="tag-suggestions-panel mt-8">
                    <p className="micro tag-suggestions-label">Suggestions</p>
                    <ul id={listboxId} className="tag-suggestions" role="listbox" aria-label="Tag suggestions">
                        {visibleSuggestions.map((s) => (
                            <li key={s} role="presentation">
                                <button
                                    type="button"
                                    className="tag-suggestion"
                                    role="option"
                                    aria-selected={false}
                                    onClick={() => submit(s)}
                                >
                                    + {s}
                                </button>
                            </li>
                        ))}
                    </ul>
                    <p className="micro tag-suggestions-hint">
                        Press Enter to add the first suggestion, or click a tag.
                    </p>
                </div>
            )}
        </div>
    );
}

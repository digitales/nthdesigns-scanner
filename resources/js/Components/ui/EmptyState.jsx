import Icon from './Icons';

export default function EmptyState({ icon, title, sub, action }) {
    return (
        <div
            style={{
                padding: '72px 24px',
                textAlign: 'center',
                border: '1px dashed var(--color-line-strong)',
                borderRadius: 6,
                background: 'var(--color-paper)',
            }}
        >
            {icon && (
                <div
                    style={{
                        width: 40,
                        height: 40,
                        margin: '0 auto 16px',
                        borderRadius: 999,
                        background: 'var(--color-paper-2)',
                        border: '1px solid var(--color-line)',
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: 'var(--color-stone-500)',
                    }}
                >
                    <Icon d={icon} size={16} />
                </div>
            )}
            <h3 style={{ fontFamily: 'var(--font-serif)', fontWeight: 500, fontSize: 22, margin: '0 0 8px' }}>
                {title}
            </h3>
            {sub && (
                <p style={{ color: 'var(--color-stone-600)', margin: '0 0 20px', maxWidth: 380, marginLeft: 'auto', marginRight: 'auto' }}>
                    {sub}
                </p>
            )}
            {action}
        </div>
    );
}

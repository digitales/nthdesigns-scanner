import Icon, { Icons } from './Icons';

export default function PageHeader({ eyebrow, title, sub, actions, back, onBack }) {
    return (
        <div className="page-header">
            <div>
                {back && (
                    <button type="button" className="back-link" onClick={onBack}>
                        <Icon d={Icons.ArrowLeft} size={11} />
                        {back}
                    </button>
                )}
                {eyebrow && <div className="eyebrow page-header-eyebrow">{eyebrow}</div>}
                <h1>{title}</h1>
                {sub && <p>{sub}</p>}
            </div>
            {actions && <div className="header-actions">{actions}</div>}
        </div>
    );
}

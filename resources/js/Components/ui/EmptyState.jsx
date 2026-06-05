import Icon from './Icons';

export default function EmptyState({ icon, title, sub, action }) {
    return (
        <div className="empty-state">
            {icon && (
                <div className="empty-state-icon">
                    <Icon d={icon} size={16} />
                </div>
            )}
            <h3 className="empty-state-title">
                {title}
            </h3>
            {sub && (
                <p className="empty-state-sub">
                    {sub}
                </p>
            )}
            {action}
        </div>
    );
}

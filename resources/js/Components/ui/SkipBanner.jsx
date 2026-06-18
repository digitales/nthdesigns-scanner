const KIND_CLASS = {
    default: 'skip-banner',
    success: 'skip-banner banner-positive banner-success',
    critical: 'skip-banner skip-banner--critical',
};

export default function SkipBanner({ kind = 'default', icon = null, children, className = '' }) {
    const classes = [KIND_CLASS[kind] ?? KIND_CLASS.default, className].filter(Boolean).join(' ');

    return (
        <div className={classes} role="status">
            {icon}
            <span>{children}</span>
        </div>
    );
}

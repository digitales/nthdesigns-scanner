import Icon from './Icons';

export default function Button({
    kind = 'secondary',
    size = 'md',
    icon,
    iconRight,
    disabled,
    className = '',
    children,
    type = 'button',
    ...rest
}) {
    const sizeClass = size === 'sm' ? ' btn-sm' : size === 'xs' ? ' btn-xs' : size === 'lg' ? ' btn-lg' : '';

    return (
        <button
            type={type}
            className={`btn btn-${kind}${sizeClass} ${className}`.trim()}
            disabled={disabled}
            {...rest}
        >
            {icon ? <Icon d={icon} className="ico" /> : null}
            {children}
            {iconRight ? <Icon d={iconRight} className="ico" /> : null}
        </button>
    );
}

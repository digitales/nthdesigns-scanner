import Icon from './Icons';

export default function IconButton({
    icon,
    title,
    disabled = false,
    className = '',
    as,
    href,
    children,
    ...rest
}) {
    const classNames = `btn-icon ${className}`.trim();

    if (as) {
        const Comp = as;

        return (
            <Comp className={classNames} title={title} href={href} {...rest}>
                <Icon d={icon} />
                {children}
            </Comp>
        );
    }

    if (href) {
        return (
            <a className={classNames} title={title} href={href} {...rest}>
                <Icon d={icon} />
                {children}
            </a>
        );
    }

    return (
        <button
            type="button"
            className={classNames}
            title={title}
            disabled={disabled}
            {...rest}
        >
            <Icon d={icon} />
            {children}
        </button>
    );
}

export function MetaList({ className = '', children, ...rest }) {
    return (
        <ul className={`meta-list ${className}`.trim()} {...rest}>
            {children}
        </ul>
    );
}

export function SplitRow({ as: Tag = 'li', className = '', children, ...rest }) {
    return (
        <Tag className={`split-row ${className}`.trim()} {...rest}>
            {children}
        </Tag>
    );
}

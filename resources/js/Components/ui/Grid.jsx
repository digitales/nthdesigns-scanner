const COLS_CLASS = {
    1: 'grid--cols-1',
    2: 'grid--cols-2',
    auto: 'grid--cols-auto',
    hours: 'grid--cols-hours',
};

export default function Grid({
    as: Tag = 'div',
    cols = 1,
    gap = 16,
    className = '',
    style,
    children,
    ...rest
}) {
    const classes = [
        'grid',
        COLS_CLASS[cols] ?? '',
        gap === 32 ? 'grid--gap-32' : gap === 8 ? 'grid--gap-8' : 'grid--gap-16',
        className,
    ].filter(Boolean).join(' ');

    return (
        <Tag className={classes} style={style} {...rest}>
            {children}
        </Tag>
    );
}

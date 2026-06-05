const COLS_CLASS = {
    1: 'grid--cols-1',
    2: 'grid--cols-2',
    3: 'grid--cols-3',
    auto: 'grid--cols-auto',
    hours: 'grid--cols-hours',
};

const GAP_CLASS = {
    8: 'grid--gap-8',
    10: 'grid--gap-10',
    12: 'grid--gap-12',
    16: 'grid--gap-16',
    32: 'grid--gap-32',
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
        GAP_CLASS[gap] ?? 'grid--gap-16',
        className,
    ].filter(Boolean).join(' ');

    return (
        <Tag className={classes} style={style} {...rest}>
            {children}
        </Tag>
    );
}

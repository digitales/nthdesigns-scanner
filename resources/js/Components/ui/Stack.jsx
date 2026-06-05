const GAP_CLASS = {
    3: 'stack--gap-3',
    4: 'stack--gap-4',
    8: 'stack--gap-8',
    10: 'stack--gap-10',
    12: 'stack--gap-12',
    16: 'stack--gap-16',
    24: 'stack--gap-24',
    32: 'stack--gap-32',
};

const ALIGN_CLASS = {
    start: 'stack--align-start',
    center: 'stack--align-center',
    end: 'stack--align-end',
    stretch: 'stack--align-stretch',
};

const JUSTIFY_CLASS = {
    start: 'stack--justify-start',
    center: 'stack--justify-center',
    end: 'stack--justify-end',
    between: 'stack--justify-between',
};

export default function Stack({
    as: Tag = 'div',
    direction = 'column',
    gap = 16,
    align,
    justify,
    wrap = false,
    className = '',
    style,
    children,
    ...rest
}) {
    const classes = [
        'stack',
        direction === 'row' ? 'stack--row' : 'stack--col',
        GAP_CLASS[gap] ?? '',
        align ? ALIGN_CLASS[align] : '',
        justify ? JUSTIFY_CLASS[justify] : '',
        wrap ? 'stack--wrap' : '',
        className,
    ].filter(Boolean).join(' ');

    return (
        <Tag className={classes} style={style} {...rest}>
            {children}
        </Tag>
    );
}

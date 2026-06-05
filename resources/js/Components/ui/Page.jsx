const WIDTH_CLASS = {
    wide: 'page--wide',
    medium: 'page--medium',
    narrow: 'page--narrow',
    compact: 'page--compact',
};

export default function Page({ width, className = '', children, ...rest }) {
    const classes = [
        'page',
        width ? WIDTH_CLASS[width] : '',
        className,
    ].filter(Boolean).join(' ');

    return (
        <main className={classes} {...rest}>
            {children}
        </main>
    );
}

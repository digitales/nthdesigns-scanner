export default function Card({ title, pad = true, className = '', children, ...rest }) {
    return (
        <div className={`card${pad ? ' card-pad' : ''} ${className}`.trim()} {...rest}>
            {title ? <div className="card-title">{title}</div> : null}
            {children}
        </div>
    );
}

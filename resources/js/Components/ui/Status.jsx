export default function Status({ kind = 'ready', children }) {
    return (
        <span className={`status ${kind}`}>
            <span className="led" />
            {children}
        </span>
    );
}

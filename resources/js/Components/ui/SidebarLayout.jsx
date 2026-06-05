import { Children } from 'react';

export default function SidebarLayout({ children, className = '' }) {
    const [main, aside] = Children.toArray(children);

    return (
        <div className={`sidebar-layout ${className}`.trim()}>
            <div>{main}</div>
            <aside className="sidebar-aside">{aside}</aside>
        </div>
    );
}

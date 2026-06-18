export const Icons = {
    ArrowLeft: 'M12 4l-8 8 8 8M4 12h12',
    ArrowRight: 'M4 4l8 8-8 8',
    ChevronD: 'M4 6l4 4 4-4',
    ChevronU: 'M4 10l4-4 4 4',
    ChevronR: 'M6 4l4 4-4 4',
    Plus: 'M8 2v12M2 8h12',
    Download: 'M8 2v9M5 11l3 3 3-3M2 14h12',
    Copy: 'M5 3h7v7H5zM4 5H2v9h9v-2',
    External: 'M10 2h4v4M6 10l8-8M14 6v8H2V2h8',
    Map: 'M3 4l3-1 4 2 3-1v9l-3 1-4-2-3 1zM6 3v9M10 5v9',
    Eye: 'M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5S1 8 1 8zM8 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4z',
    Refresh: 'M14 7A6 6 0 0 0 3 4M2 9a6 6 0 0 0 11 3M14 2v5h-5M2 14V9h5',
    X: 'M4 4l8 8M12 4l-8 8',
    Lock: 'M4 7V5a4 4 0 0 1 8 0v2M3 7h10v7H3z',
    Send: 'M14 2L7 9M14 2l-5 12-2-5-5-2z',
    Mail: 'M2 4h12v8H2zM2 4l6 5 6-5',
    Bell: 'M8 1.5a4.5 4.5 0 0 0-4.5 4.5v1.5c0 .5-.2 1-.5 1.4L2 12.2a1 1 0 0 0 .9 1.5h10.2a1 1 0 0 0 .9-1.5l-1-1.8c-.3-.4-.5-.9-.5-1.4V6A4.5 4.5 0 0 0 8 1.5zM6.5 13a1.5 1.5 0 0 0 3 0',
    Search: 'M7 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM11 11l4 4',
    List: 'M2 4h12M2 8h12M2 12h8',
    Filter: 'M2 3h12l-4 5v4l-4 2V8z',
    Bookmark: 'M3 2h10v12l-5-3-5 3z',
    Share: 'M10 2h4v4M6 10l8-8M14 6v8H2V2h8',
};

export default function Icon({ d, size = 16, className = '' }) {
    return (
        <svg
            viewBox="0 0 16 16"
            width={size}
            height={size}
            className={className}
            aria-hidden="true"
        >
            <path d={d} stroke="currentColor" strokeWidth="1.6" fill="none" />
        </svg>
    );
}

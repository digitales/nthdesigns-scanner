import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EmptyState, Icons, PageHeader } from '@/Components/ui';

export default function FindIndex({ query = '', status, sections = [] }) {
    const title = query ? `Search results for “${query}”` : 'Search';

    return (
        <AuthenticatedLayout>
            <Head title={query ? `Search: ${query}` : 'Search'} />

            <main className="page page-wide">
                <PageHeader eyebrow="Find" title={title} />

                {status === 'too_short' && (
                    <EmptyState title="Enter at least 2 characters to search." />
                )}

                {status === 'empty' && (
                    <EmptyState
                        icon={Icons.Search}
                        title={`No results for “${query}”.`}
                        sub="Try a business name, website, or niche."
                    />
                )}

                {status === 'results' && (
                    <div className="find-results">
                        {sections.map((section) => (
                            <section key={section.key} className="find-section">
                                <h2 className="find-section-title">
                                    {section.label} ({section.items.length})
                                </h2>
                                <ul className="find-section-list">
                                    {section.items.map((item, index) => (
                                        <li key={`${section.key}-${index}`}>
                                            <Link href={item.href} className="find-result-row">
                                                <span className="find-result-title">{item.title}</span>
                                                {item.subtitle ? (
                                                    <span className="find-result-subtitle">{item.subtitle}</span>
                                                ) : null}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </main>
        </AuthenticatedLayout>
    );
}

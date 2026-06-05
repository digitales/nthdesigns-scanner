import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SearchHistoryCard from '@/Components/Search/SearchHistoryCard';
import { Button, EmptyState, Icons, Page, PageHeader, Pagination } from '@/Components/ui';

export default function SearchHistory({ searches, pagination }) {
    return (
        <AuthenticatedLayout>
            <Head title="Search history" />

            <Page width="compact">
                <PageHeader
                    back="Back to search"
                    onBack={() => router.visit('/search')}
                    eyebrow="Search history"
                    title="Your searches"
                    sub="All scans you've run."
                />

                {searches.length === 0 ? (
                    <EmptyState
                        icon={Icons.Search}
                        title="No searches yet."
                        sub="Run a niche scan or single-site audit to get started."
                        action={
                            <Link href="/search">
                                <Button kind="secondary" size="sm">Go to search</Button>
                            </Link>
                        }
                    />
                ) : (
                    <>
                        <ul style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                            {searches.map((search) => (
                                <li key={search.id}>
                                    <SearchHistoryCard search={search} showFound />
                                </li>
                            ))}
                        </ul>
                        <Pagination pagination={pagination} href="/searches" />
                    </>
                )}
            </Page>
        </AuthenticatedLayout>
    );
}

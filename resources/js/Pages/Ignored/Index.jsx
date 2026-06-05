import { Head, Link, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
  Button,
  DataTable,
  EmptyState,
  Field,
  FilterBar,
  Icons,
  PageHeader,
  Pagination,
  RowActions,
  ScoreBadge,
  Select,
  Status,
} from "@/Components/ui";

export default function IgnoredIndex({
  entries,
  filters,
  pagination,
  reasonOptions,
}) {
  const submitFilters = (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const params = Object.fromEntries(form.entries());
    if (!params.reason) delete params.reason;
    router.get("/ignored", params, { preserveState: true });
  };

  const unignore = (row) => {
    if (row.prospect_id) {
      router.delete(`/prospects/${row.prospect_id}/ignore`, {
        preserveScroll: true,
      });
      return;
    }
    router.delete(`/ignored/${row.id}`, { preserveScroll: true });
  };

  return (
    <AuthenticatedLayout>
      <Head title="Ignored prospects" />

      <main className="page page-wide">
        <PageHeader
          eyebrow="Ignored prospects"
          title={`${pagination.total} ignored`}
          sub="Businesses you have excluded from future scans. Open a row to review details or undo ignore."
        />

        <FilterBar onSubmit={submitFilters}>
          <Field label="Reason">
            <Select name="reason" defaultValue={filters.reason ?? ""}>
              {reasonOptions.map((o) => (
                <option key={o.value || "all"} value={o.value}>
                  {o.label}
                </option>
              ))}
            </Select>
          </Field>
          <div className="filter-action">
            <Button kind="primary" size="sm" type="submit">
              Apply
            </Button>
            <Link href="/ignored" className="micro">
              Reset
            </Link>
          </div>
        </FilterBar>

        {entries.length === 0 ? (
          <EmptyState
            icon={Icons.Search}
            title="No ignored prospects."
            sub="Ignore a business from its prospect page when it should not appear in future scans."
          />
        ) : (
          <DataTable>
            <thead>
              <tr>
                <th>Business</th>
                <th>Reason</th>
                <th>Note</th>
                <th>Last seen</th>
                <th>Ignored</th>
                <th style={{ textAlign: "right" }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {entries.map((row) => (
                <tr
                  key={row.id}
                  className={row.prospect_id ? "clickable" : ""}
                  onClick={() =>
                    row.prospect_id &&
                    router.visit(`/prospects/${row.prospect_id}`)
                  }
                >
                  <td className="biz">
                    {row.business_name ?? (
                      <span className="micro" title={row.place_id}>
                        Unknown business
                      </span>
                    )}
                    {row.niche && row.city && (
                      <div className="micro">
                        {row.niche} · {row.city}
                      </div>
                    )}
                  </td>
                  <td>
                    <Status kind="pending">{row.reason_label}</Status>
                  </td>
                  <td className="micro" style={{ maxWidth: 240 }}>
                    {row.note ?? "—"}
                  </td>
                  <td>
                    {row.combined_score != null ? (
                      <ScoreBadge value={row.combined_score} withBar={false} />
                    ) : (
                      <span className="micro">—</span>
                    )}
                  </td>
                  <td className="micro">{row.ignored_at}</td>
                  <td
                    onClick={(e) => e.stopPropagation()}
                    style={{ textAlign: "right" }}
                  >
                    <RowActions>
                      {row.prospect_id && (
                        <Link
                          href={`/prospects/${row.prospect_id}`}
                          className="btn-ghost btn-xs"
                        >
                          View
                        </Link>
                      )}
                      <button
                        type="button"
                        className="btn-ghost btn-xs"
                        onClick={() => unignore(row)}
                      >
                        Undo ignore
                      </button>
                    </RowActions>
                  </td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        )}

        {pagination?.last_page > 1 && (
          <Pagination pagination={pagination} href="/ignored" />
        )}
      </main>
    </AuthenticatedLayout>
  );
}

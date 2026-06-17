import { Head, Link, router } from "@inertiajs/react";
import { Fragment, useEffect, useMemo, useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import QualificationControl, {
  QualificationDetails,
  qualifyProspectsWithStagger,
} from "@/Components/QualificationControl";
import { useProgressReload } from "@/hooks/useProgressReload";
import {
  AnglePill,
  Button,
  Card,
  DataTable,
  EmptyState,
  Field,
  FilterBar,
  Grid,
  IconButton,
  Icons,
  Input,
  PageHeader,
  RowActions,
  ScoreBadge,
  Select,
  Stack,
  Toast,
} from "@/Components/ui";

export default function SavedIndex({ prospects, warmLeads, filters, meta }) {
  const [toast, setToast] = useState(null);
  const [expandedQualification, setExpandedQualification] = useState(null);
  const [qualifyingIds, setQualifyingIds] = useState(() => new Set());

  const qualificationPolling = useMemo(() => {
    if (qualifyingIds.size === 0) {
      return false;
    }

    return prospects.some(
      (p) => qualifyingIds.has(p.id) && !p.qualification_status,
    );
  }, [prospects, qualifyingIds]);

  useProgressReload(qualificationPolling, ["prospects"], 5000);

  useEffect(() => {
    setQualifyingIds((prev) => {
      if (prev.size === 0) {
        return prev;
      }

      const next = new Set(prev);
      let changed = false;

      for (const id of prev) {
        const prospect = prospects.find((p) => p.id === id);
        if (prospect?.qualification_status) {
          next.delete(id);
          changed = true;
        }
      }

      return changed ? next : prev;
    });
  }, [prospects]);

  const unqualifiedCount = useMemo(
    () => prospects.filter((p) => !p.qualification_status).length,
    [prospects],
  );

  const markQualifying = (id) => {
    setQualifyingIds((prev) => new Set(prev).add(id));
  };

  const qualifyAll = () => {
    const ids = prospects
      .filter((p) => !p.qualification_status)
      .map((p) => p.id);

    qualifyProspectsWithStagger(ids, markQualifying);
  };

  const submitFilters = (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const params = Object.fromEntries(form.entries());
    if (!params.warm) delete params.warm;
    router.get("/saved", params, { preserveState: true });
  };

  const addToOutreach = (prospectId) => {
    router.post("/outreach/selections", { prospect_ids: [prospectId] });
  };

  const copyUrl = (url) => {
    navigator.clipboard.writeText(url);
    setToast(`${url.replace(/^https?:\/\/[^/]+/, "")} copied`);
  };

  const exportCsv = () => {
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "/exports";
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "_token";
      input.value = csrf;
      form.appendChild(input);
    }
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== "" && value != null) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = key;
        input.value = value;
        form.appendChild(input);
      }
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
  };

  return (
    <AuthenticatedLayout>
      <Head title="Saved prospects" />

      <main className="page page-wide">
        <PageHeader
          eyebrow="Saved prospects"
          title={`${meta.total} prospect${meta.total !== 1 ? "s" : ""} across searches.`}
          sub="Filter by niche, score, or warm-lead status. Export matches your current filters as CSV."
          actions={
            <>
              {unqualifiedCount > 0 && (
                <Button kind="secondary" size="sm" onClick={qualifyAll}>
                  Qualify all ({unqualifiedCount})
                </Button>
              )}
              <Button
                kind="secondary"
                size="sm"
                icon={Icons.Download}
                onClick={exportCsv}
              >
                Export CSV
              </Button>
            </>
          }
        />

        {warmLeads.length > 0 && !filters.warm && (
          <section className="warm-panel">
            <Stack direction="row" justify="between" align="center" className="mb-16">
              <div className="card-title card-title--flush">Warm leads</div>
              <Link href="/saved?warm=1" className="micro text-accent-deep">
                Filter to warm →
              </Link>
            </Stack>
            <Grid cols={3} gap={12}>
              {warmLeads.slice(0, 3).map((p) => (
                <Link
                  key={p.id}
                  href={`/prospects/${p.id}`}
                  className="warm-lead-link"
                >
                  <Card pad className="card--compact">
                    <div className="warm-lead-name">{p.business_name}</div>
                    <div className="micro mt-4">
                      {p.niche} · {p.city}
                    </div>
                    <div className="mt-10">
                      <ScoreBadge value={p.combined_score} withBar={false} />
                    </div>
                  </Card>
                </Link>
              ))}
            </Grid>
          </section>
        )}

        <FilterBar onSubmit={submitFilters}>
          <Field label="From">
            <Input type="date" name="from" defaultValue={filters.from ?? ""} />
          </Field>
          <Field label="To">
            <Input type="date" name="to" defaultValue={filters.to ?? ""} />
          </Field>
          <Field label="Niche">
            <Input
              type="text"
              name="niche"
              defaultValue={filters.niche ?? ""}
            />
          </Field>
          <Field label="City">
            <Input type="text" name="city" defaultValue={filters.city ?? ""} />
          </Field>
          <Field label="Scan type">
            <Select name="scan_type" defaultValue={filters.scan_type ?? ""}>
              <option value="">Any</option>
              <option value="combined">Combined</option>
              <option value="gbp_only">GBP only</option>
              <option value="accessibility_only">Accessibility only</option>
            </Select>
          </Field>
          <Field label="Angle">
            <Select
              name="dominant_angle"
              defaultValue={filters.dominant_angle ?? ""}
            >
              <option value="">Any</option>
              <option value="gbp">GBP</option>
              <option value="accessibility">Accessibility</option>
              <option value="both">Both</option>
            </Select>
          </Field>
          <Field label="Min score">
            <Input
              type="number"
              name="min_score"
              min="0"
              max="100"
              defaultValue={filters.min_score ?? ""}
            />
          </Field>
          <Field label="Warm">
            <label className="filter-checkbox-label">
              <input
                type="checkbox"
                className="checkbox"
                name="warm"
                value="1"
                defaultChecked={!!filters.warm}
              />
              Warm only
            </label>
          </Field>
          <div className="filter-action">
            <Button kind="primary" size="sm" type="submit">
              Apply
            </Button>
            <Link href="/saved" className="micro">
              Reset
            </Link>
          </div>
        </FilterBar>

        {prospects.length === 0 ? (
          <EmptyState
            icon={Icons.Search}
            title="No prospects match these filters."
            sub="Try widening your date range or lowering the minimum score."
          />
        ) : (
          <DataTable>
            <thead>
              <tr>
                <th>Business</th>
                <th>Niche / City</th>
                <th>Combined</th>
                <th>GBP</th>
                <th>A11y</th>
                <th>Angle</th>
                <th>Qualify</th>
                <th>Outreach history</th>
                <th className="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {prospects.map((p) => (
                <Fragment key={p.id}>
                  <tr
                    className={p.is_warm ? "warm" : ""}
                    onClick={() => router.visit(`/prospects/${p.id}`)}
                  >
                    <td className="biz">{p.business_name}</td>
                    <td className="micro">
                      {p.niche} · {p.city}
                    </td>
                    <td>
                      <ScoreBadge value={p.combined_score} withBar={false} />
                    </td>
                    <td className="num">{p.gbp_score ?? "—"}</td>
                    <td className="num">{p.a11y_score ?? "—"}</td>
                    <td>
                      <AnglePill angle={p.dominant_angle} />
                    </td>
                    <td onClick={(e) => e.stopPropagation()}>
                      <QualificationControl
                        prospect={p}
                        isPending={qualifyingIds.has(p.id)}
                        isExpanded={expandedQualification === p.id}
                        onToggleExpand={() =>
                          setExpandedQualification(
                            expandedQualification === p.id ? null : p.id,
                          )
                        }
                        onQualifyStart={markQualifying}
                      />
                    </td>
                    <td onClick={(e) => e.stopPropagation()}>
                      {p.outreach_sent_label ? (
                        <div className="micro">
                          Sent {p.outreach_sent_label}
                          {p.report_viewed_label && (
                            <div className="text-accent-ink mt-4">
                              Viewed {p.report_viewed_label}
                            </div>
                          )}
                        </div>
                      ) : (
                        <span className="micro">—</span>
                      )}
                    </td>
                    <td
                      className="text-right"
                      onClick={(e) => e.stopPropagation()}
                    >
                      <RowActions>
                        {p.report_url && (
                          <IconButton
                            icon={Icons.Copy}
                            title="Copy report URL"
                            onClick={() => copyUrl(p.report_url)}
                          />
                        )}
                        <button
                          type="button"
                          className="btn-ghost btn-xs"
                          onClick={() => addToOutreach(p.id)}
                        >
                          + Queue
                        </button>
                      </RowActions>
                    </td>
                  </tr>
                  {expandedQualification === p.id && (
                    <tr className="expanded-row qualification-expanded-row">
                      <td colSpan={9}>
                        <QualificationDetails prospect={p} />
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))}
            </tbody>
          </DataTable>
        )}

        {toast && <Toast onClose={() => setToast(null)}>{toast}</Toast>}
      </main>
    </AuthenticatedLayout>
  );
}

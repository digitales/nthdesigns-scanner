import { Head, Link, router, useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import OutreachChannelCard from "@/Components/OutreachChannelCard";
import WarmupReadinessBanner from "@/Pages/Warmup/components/WarmupReadinessBanner";
import {
  AnglePill,
  Button,
  Card,
  EmptyState,
  Field,
  Grid,
  Icon,
  Icons,
  Input,
  PageHeader,
  ScoreBadge,
  Page,
  Segmented,
  SkipBanner,
} from "@/Components/ui";

export default function OutreachIndex({
  selection,
  filters = { booked: false },
  emailsByProspect,
  defaults,
  flash,
  warmup_readiness: warmupReadiness,
}) {
  const { data, setData, post, processing } = useForm({
    agency_name: defaults.agency_name,
    pitch_angle: defaults.pitch_angle,
    cpc_benchmark: defaults.cpc_benchmark,
  });

  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [refreshing, setRefreshing] = useState(false);

  const skippedCount = selection.filter((s) => !s.report_ready).length;
  const eligibleCount = selection.filter((s) => s.report_ready).length;
  const bookedCount = selection.filter((s) => s.booked).length;
  const refreshable = useMemo(
    () => selection.filter((s) => s.refresh_eligible),
    [selection],
  );

  const setBookedFilter = (booked) => {
    router.get("/outreach", booked ? { booked: 1 } : {}, { preserveState: true });
  };

  const removeFromQueue = (prospectId) => {
    router.delete(`/outreach/selections/${prospectId}`);
  };

  const [clearing, setClearing] = useState(false);

  const clearQueue = () => {
    if (!window.confirm("Clear all prospects from the outreach queue?")) {
      return;
    }

    setClearing(true);
    router.delete("/outreach/selections", {
      onFinish: () => setClearing(false),
    });
  };

  const toggleSelected = (prospectId) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(prospectId)) {
        next.delete(prospectId);
      } else {
        next.add(prospectId);
      }
      return next;
    });
  };

  const selectAllRefreshable = () => {
    setSelectedIds(new Set(refreshable.map((s) => s.prospect_id)));
  };

  const clearSelection = () => setSelectedIds(new Set());

  const generateAll = (e) => {
    e.preventDefault();
    post("/outreach/generate");
  };

  const refreshSelected = (e) => {
    e.preventDefault();
    if (selectedIds.size === 0) {
      return;
    }

    setRefreshing(true);
    router.post(
      "/outreach/refresh",
      {
        prospect_ids: [...selectedIds],
        pitch_angle: data.pitch_angle,
        agency_name: data.agency_name,
        cpc_benchmark: data.cpc_benchmark,
      },
      {
        onFinish: () => setRefreshing(false),
        onSuccess: () => clearSelection(),
      },
    );
  };

  const reportStatusLine = (item) => {
    if (item.booked_label) {
      return item.booked_label;
    }

    if (!item.report_ready) {
      return "No report";
    }

    const age = item.report_age_label ? `Report · ${item.report_age_label}` : "Report ready";
    return item.report_stale ? `${age} · Stale` : age;
  };

  return (
    <AuthenticatedLayout>
      <Head title="Outreach" />

      <Page width="wide" className="page-wide">
        <PageHeader
          eyebrow="Outreach workspace"
          title={`${selection.length} prospect${selection.length !== 1 ? "s" : ""} in queue.`}
          sub="Batch-generate personalised outreach. Prospects without a report or contact path are skipped automatically."
        />

        <WarmupReadinessBanner readiness={warmupReadiness} />

        {flash?.success && (
          <SkipBanner kind="success">{flash.success}</SkipBanner>
        )}

        {flash?.skipped?.length > 0 && (
          <SkipBanner icon={<Icon d={Icons.Lock} size={14} />}>
            Skipped: {flash.skipped.join(", ")}
          </SkipBanner>
        )}

        {skippedCount > 0 && (
          <SkipBanner icon={<Icon d={Icons.Lock} size={14} />}>
            {skippedCount} prospect{skippedCount !== 1 ? "s" : ""} will be
            skipped — outreach requires an embedded link.
          </SkipBanner>
        )}

        <div className="outreach-layout">
          <section>
            <div className="queue-header">
              <div className="card-title card-title-flush">Queue</div>
              <Segmented
                value={filters.booked ? "booked" : "all"}
                onChange={(value) => setBookedFilter(value === "booked")}
                options={[
                  { value: "all", label: "All" },
                  { value: "booked", label: `Booked${bookedCount ? ` (${bookedCount})` : ""}` },
                ]}
              />
              {refreshable.length > 0 && (
                <div className="queue-selection-actions">
                  <button type="button" className="btn-ghost btn-xs" onClick={selectAllRefreshable}>
                    Select all refreshable
                  </button>
                  {selectedIds.size > 0 && (
                    <>
                      <span className="micro">{selectedIds.size} selected</span>
                      <button type="button" className="btn-ghost btn-xs" onClick={clearSelection}>
                        Clear
                      </button>
                    </>
                  )}
                </div>
              )}
              {selection.length > 0 && (
                <Button
                  kind="ghost"
                  size="sm"
                  disabled={clearing}
                  onClick={clearQueue}
                >
                  {clearing ? "Clearing…" : "Clear all"}
                </Button>
              )}
            </div>

            {selection.length === 0 ? (
              <EmptyState
                icon={Icons.Mail}
                title="Queue is empty."
                sub="Add prospects from search results or saved list."
                action={
                  <Link href="/search">
                    <Button kind="secondary" size="sm">
                      Go to search
                    </Button>
                  </Link>
                }
              />
            ) : (
              <ul className="queue-list">
                {selection.map((item) => (
                  <li key={item.id} className="queue-chip">
                    {item.refresh_eligible && (
                      <input
                        type="checkbox"
                        className="queue-chip-checkbox"
                        checked={selectedIds.has(item.prospect_id)}
                        onChange={() => toggleSelected(item.prospect_id)}
                        onClick={(e) => e.stopPropagation()}
                        aria-label={`Select ${item.business_name} for refresh`}
                      />
                    )}
                    <Link
                      href={`/prospects/${item.prospect_id}?from=outreach`}
                      className="queue-link"
                    >
                      <div className="queue-chip-title">
                        {item.business_name}
                      </div>
                      <div className="queue-chip-meta">
                        <ScoreBadge
                          value={item.combined_score}
                          withBar={false}
                        />
                        <AnglePill angle={item.dominant_angle} />
                        {item.outreach_status === "sent" && (
                          <span className="badge badge--queue">Sent</span>
                        )}
                      </div>
                      <div className={`micro mt-4${item.report_stale ? " text-warning" : ""}`}>
                        {reportStatusLine(item)}
                      </div>
                      {item.outreach_status !== "sent" && item.outreach_status_label && (
                        <div className="micro text-stone mt-4">
                          Outreach · {item.outreach_status_label}
                        </div>
                      )}
                      {item.booked_note && (
                        <div className="micro text-stone mt-4">Note: {item.booked_note}</div>
                      )}
                      {item.booked && !item.booked_confirmation_sent && (
                        <div className="micro text-stone mt-4">Confirmation mail pending</div>
                      )}
                    </Link>
                    <button
                      type="button"
                      className="remove"
                      onClick={() => removeFromQueue(item.prospect_id)}
                      aria-label="Remove"
                    >
                      ×
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section>
            <Card title="Generate outreach" className="mb-24">
              <form onSubmit={generateAll}>
                <Grid cols={2} gap={16} className="mb-16">
                  <Field label="Pitch angle">
                    <Segmented
                      value={data.pitch_angle}
                      onChange={(v) => setData("pitch_angle", v)}
                      options={[
                        { value: "auto", label: "Auto" },
                        { value: "gbp", label: "GBP" },
                        { value: "accessibility", label: "A11y" },
                        { value: "combined", label: "Both" },
                      ]}
                    />
                  </Field>
                  <Field label="Agency name" hint="optional">
                    <Input
                      value={data.agency_name}
                      onChange={(e) => setData("agency_name", e.target.value)}
                      placeholder="nthdesigns"
                    />
                  </Field>
                </Grid>
                <Field
                  label="CPC benchmark"
                  hint={
                    defaults.cpc_mixed
                      ? "Queue spans multiple searches — each prospect uses its search CPC unless you override here"
                      : defaults.cpc_from_search
                        ? "Pre-filled from search — override for this batch if needed"
                        : "optional · GBP pitches · set on search or enter here"
                  }
                >
                  <div className="input-with-prefix">
                    <span className="prefix">£</span>
                    <Input
                      type="number"
                      min="0"
                      step="0.01"
                      value={data.cpc_benchmark}
                      onChange={(e) => setData("cpc_benchmark", e.target.value)}
                    />
                  </div>
                </Field>
                {defaults.cpc_mixed && (
                  <p className="micro text-stone mb-16">
                    Mixed queue: leave blank to use each prospect&apos;s search CPC, or enter a value to override all.
                  </p>
                )}
                <div className="mt-20 outreach-form-actions">
                  <Button
                    kind="primary"
                    size="lg"
                    type="submit"
                    disabled={processing || refreshing || selection.length === 0}
                    icon={processing ? undefined : Icons.Send}
                    className="w-full justify-center"
                  >
                    {processing
                      ? "Generating…"
                      : `Generate for ${eligibleCount} prospect${eligibleCount !== 1 ? "s" : ""}`}
                  </Button>
                  <Button
                    kind="secondary"
                    size="lg"
                    type="button"
                    disabled={refreshing || processing || selectedIds.size === 0}
                    onClick={refreshSelected}
                    className="w-full justify-center"
                  >
                    {refreshing
                      ? "Refreshing…"
                      : `Refresh selected (${selectedIds.size})`}
                  </Button>
                </div>
              </form>
            </Card>

            {selection.map((item) => {
              const emails = emailsByProspect[item.prospect_id] ?? [];
              if (emails.length === 0) return null;
              return (
                <div key={item.prospect_id} className="mb-24">
                  <h3 className="body-14-medium mb-12">
                    {item.business_name}
                  </h3>
                  {emails.map((email) => (
                    <div key={email.id} className="mb-16">
                      <OutreachChannelCard
                        email={{
                          ...email,
                          combined_score: item.combined_score,
                        }}
                        reportUrl={item.report_url}
                        performanceScore={item.performance_score}
                      />
                    </div>
                  ))}
                </div>
              );
            })}
          </section>
        </div>
      </Page>
    </AuthenticatedLayout>
  );
}

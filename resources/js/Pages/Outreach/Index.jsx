import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import OutreachEmailCard from "@/Components/OutreachEmailCard";
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
} from "@/Components/ui";

export default function OutreachIndex({
  selection,
  emailsByProspect,
  defaults,
  flash,
}) {
  const { data, setData, post, processing } = useForm({
    agency_name: defaults.agency_name,
    pitch_angle: defaults.pitch_angle,
    cpc_benchmark: defaults.cpc_benchmark,
  });

  const skippedCount = selection.filter((s) => !s.report_ready).length;
  const eligibleCount = selection.filter((s) => s.report_ready).length;

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

  const generateAll = (e) => {
    e.preventDefault();
    post("/outreach/generate");
  };

  return (
    <AuthenticatedLayout>
      <Head title="Outreach" />

      <Page width="wide" className="page-wide">
        <PageHeader
          eyebrow="Outreach workspace"
          title={`${selection.length} prospect${selection.length !== 1 ? "s" : ""} in queue.`}
          sub="Batch-generate personalised emails. Prospects without a report are skipped automatically."
        />

        {flash?.success && (
          <div className="skip-banner banner-positive banner-success">
            {flash.success}
          </div>
        )}

        {flash?.skipped?.length > 0 && (
          <div className="skip-banner">
            <Icon d={Icons.Lock} size={14} />
            Skipped (no report): {flash.skipped.join(", ")}
          </div>
        )}

        {skippedCount > 0 && (
          <div className="skip-banner">
            <Icon d={Icons.Lock} size={14} />
            {skippedCount} prospect{skippedCount !== 1 ? "s" : ""} will be
            skipped — outreach requires an embedded link.
          </div>
        )}

        <div className="outreach-layout">
          <section>
            <div className="queue-header">
              <div className="card-title card-title-flush">Queue</div>
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
                      </div>
                      <div className="micro mt-4">
                        {item.booked_label
                          ? item.booked_label
                          : item.report_ready
                            ? "Report ready"
                            : "No report"}
                      </div>
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
            <Card title="Generate emails" className="mb-24">
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
                <Field label="CPC benchmark" hint="optional">
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
                <div className="mt-20">
                  <Button
                    kind="primary"
                    size="lg"
                    type="submit"
                    disabled={processing || selection.length === 0}
                    icon={processing ? undefined : Icons.Send}
                    className="w-full justify-center"
                  >
                    {processing
                      ? "Generating…"
                      : `Generate ${eligibleCount} email${eligibleCount !== 1 ? "s" : ""}`}
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
                      <OutreachEmailCard
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

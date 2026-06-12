import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import SearchHistoryCard from "@/Components/Search/SearchHistoryCard";
import {
  Button,
  Card,
  Field,
  FormError,
  Grid,
  Input,
  Page,
  PageHeader,
  Select,
  SidebarLayout,
  Stack,
} from "@/Components/ui";

const SCAN_TYPES = [
  { value: "gbp_only", title: "GBP only", sub: "Visibility audit · ~30s/biz" },
  {
    value: "accessibility_only",
    title: "Accessibility only",
    sub: "WCAG 2.2 audit · ~90s/biz",
  },
  { value: "combined", title: "Combined", sub: "Both signals · ~2m/biz" },
];

const SCAN_INFO = {
  gbp_only:
    "Discovers businesses on Google, then scores each Google Business Profile. Typically completes in under a minute per business.",
  accessibility_only:
    "Runs a full WCAG 2.2 audit on each website. Allow around 90 seconds per business.",
  combined:
    "GBP scoring plus accessibility audit in sequence. Plan for roughly two minutes per business.",
};

export default function SearchIndex({
  recentSearches,
  defaults = { country: "GB" },
  googleAdsCpcAvailable = false,
}) {
  const { flash } = usePage().props;
  const { data, setData, post, processing, errors } = useForm({
    niche: "",
    city: "",
    country: defaults.country,
    scan_type: "combined",
    cpc_benchmark: "",
  });
  const [cpcLoading, setCpcLoading] = useState(false);
  const [cpcFetching, setCpcFetching] = useState(false);

  useEffect(() => {
    const marketCpc = flash?.market_cpc;
    if (!marketCpc?.cpc_benchmark) {
      return;
    }
    setData("cpc_benchmark", marketCpc.cpc_benchmark);
  }, [flash?.market_cpc, setData]);

  const marketPayload = () => ({
    niche: data.niche,
    city: data.city,
    country: data.country,
  });

  const loadSavedCpc = () => {
    if (!data.niche.trim() || !data.city.trim()) {
      return;
    }
    setCpcLoading(true);
    router.post("/market-cpc/load", marketPayload(), {
      preserveScroll: true,
      onFinish: () => setCpcLoading(false),
    });
  };

  const fetchCpcFromGoogleAds = () => {
    if (!data.niche.trim() || !data.city.trim()) {
      return;
    }
    setCpcFetching(true);
    router.post("/market-cpc/fetch", marketPayload(), {
      preserveScroll: true,
      onFinish: () => setCpcFetching(false),
    });
  };

  const submit = (e) => {
    e.preventDefault();
    post("/searches");
  };

  const directForm = useForm({ website_url: "" });

  const submitDirect = (e) => {
    e.preventDefault();
    directForm.post("/searches/direct");
  };

  const keywordPreview = flash?.market_cpc?.cpc_keywords ?? [];

  return (
    <AuthenticatedLayout>
      <Head title="New search" />

      <Page width="medium">
        <PageHeader
          eyebrow="New search"
          title="Run a prospect scan."
          sub="We'll discover up to 25 businesses on Google, score their Google Business Profile, then audit their websites for WCAG 2.2 accessibility violations."
        />

        <SidebarLayout>
          <div>
            <Card title="Parameters">
              <form onSubmit={submit}>
                <Grid cols={2} gap={16} className="mb-18">
                  <Field label="Niche" hint="local trade or profession">
                    <Input
                      value={data.niche}
                      onChange={(e) => setData("niche", e.target.value)}
                      placeholder="e.g. Dental practice"
                      required
                    />
                    <FormError message={errors.niche} />
                  </Field>
                  <Field label="City" hint="UK city or town">
                    <Input
                      value={data.city}
                      onChange={(e) => setData("city", e.target.value)}
                      placeholder="e.g. Birmingham"
                      required
                    />
                    <FormError message={errors.city} />
                  </Field>
                </Grid>

                <div className="mb-24">
                  <Field label="Country">
                    <Select
                      value={data.country}
                      onChange={(e) => setData("country", e.target.value)}
                    >
                      <option value="GB">United Kingdom</option>
                      <option value="IE">Ireland</option>
                      <option value="US">United States</option>
                    </Select>
                  </Field>
                </div>

                <div className="mb-24">
                  <Field
                    label="CPC benchmark"
                    hint="Optional · GBP outreach only · lookup is separate from Run scan (no Places fees)"
                  >
                    <div className="input-with-prefix">
                      <span className="prefix">£</span>
                      <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={data.cpc_benchmark}
                        onChange={(e) => setData("cpc_benchmark", e.target.value)}
                        placeholder="e.g. 8.50"
                      />
                    </div>
                    <Stack direction="row" gap={8} className="mt-8">
                      <Button
                        kind="ghost"
                        size="sm"
                        type="button"
                        disabled={cpcLoading || !data.niche || !data.city}
                        onClick={loadSavedCpc}
                      >
                        {cpcLoading ? "Loading…" : "Load saved"}
                      </Button>
                      {googleAdsCpcAvailable && (
                        <Button
                          kind="ghost"
                          size="sm"
                          type="button"
                          disabled={cpcFetching || !data.niche || !data.city}
                          onClick={fetchCpcFromGoogleAds}
                        >
                          {cpcFetching ? "Fetching…" : "Fetch from Google Ads"}
                        </Button>
                      )}
                    </Stack>
                    {keywordPreview.length > 0 && (
                      <p className="micro text-stone mt-8 mb-0">
                        Keywords: {keywordPreview.join(", ")}
                      </p>
                    )}
                    <FormError message={errors.cpc_benchmark} />
                  </Field>
                </div>

                {flash?.success && (
                  <p className="micro text-positive mb-16">{flash.success}</p>
                )}
                {flash?.error && (
                  <p className="micro text-critical mb-16">{flash.error}</p>
                )}

                <Field label="Scan type">
                  <Grid cols={3} gap={10} className="mt-4">
                    {SCAN_TYPES.map((o) => (
                      <button
                        key={o.value}
                        type="button"
                        className={`scan-type-btn${data.scan_type === o.value ? " active" : ""}`}
                        onClick={() => setData("scan_type", o.value)}
                      >
                        <div className="title">{o.title}</div>
                        <div className="sub">{o.sub}</div>
                      </button>
                    ))}
                  </Grid>
                </Field>

                <div className="scan-info-panel">
                  {SCAN_INFO[data.scan_type]}
                </div>

                <div className="mt-24">
                  <Button
                    kind="primary"
                    size="lg"
                    type="submit"
                    disabled={processing}
                    className="w-full justify-center"
                  >
                    {processing ? "Starting scan…" : "Run scan"}
                  </Button>
                </div>
              </form>
            </Card>

            <Card title="Single site audit" className="card--spaced">
              <p className="micro mb-16 line-height-relaxed">
                Paste a website URL to look up its Google Business Profile and
                run a WCAG 2.2 audit. Takes about 90 seconds.
              </p>
              <form onSubmit={submitDirect}>
                <Field label="Website URL">
                  <Input
                    value={directForm.data.website_url}
                    onChange={(e) =>
                      directForm.setData("website_url", e.target.value)
                    }
                    placeholder="https://example.co.uk"
                    required
                  />
                  <FormError message={directForm.errors.website_url} />
                </Field>
                <div className="mt-16">
                  <Button
                    kind="secondary"
                    size="lg"
                    type="submit"
                    disabled={directForm.processing}
                    className="w-full justify-center"
                  >
                    {directForm.processing
                      ? "Starting audit…"
                      : "Run single-site audit"}
                  </Button>
                </div>
              </form>
            </Card>
          </div>

          <div>
            <div className="aside-header">
              <div className="card-title">Recent searches</div>
              <Link href="/searches" className="micro">
                View all
              </Link>
            </div>
            {recentSearches.length === 0 ? (
              <p className="micro">No searches yet.</p>
            ) : (
              <Stack as="ul" gap={8}>
                {recentSearches.map((s) => (
                  <li key={s.id}>
                    <SearchHistoryCard search={s} />
                  </li>
                ))}
              </Stack>
            )}
          </div>
        </SidebarLayout>
      </Page>
    </AuthenticatedLayout>
  );
}

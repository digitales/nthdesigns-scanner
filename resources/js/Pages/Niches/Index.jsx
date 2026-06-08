import { Head, router, usePage } from "@inertiajs/react";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  nicheCityKey,
  useNicheScanStatusPoll,
} from "@/hooks/useNicheScanStatusPoll";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import NicheAnnotatePanel from "@/Components/Niches/NicheAnnotatePanel";
import NicheSamplePanel from "@/Components/Niches/NicheSamplePanel";
import ManageNichesPanel from "@/Components/Niches/ManageNichesPanel";
import {
  Button,
  DataTable,
  EmptyState,
  Field,
  FilterBar,
  Icons,
  PageHeader,
  RowActions,
  ScoreBadge,
  Segmented,
  Select,
  Status,
  Toast,
} from "@/Components/ui";

const PER_PAGE = 50;
const TOPBAR_HEIGHT = 52;

function formatPct(value) {
  if (value == null) return "—";
  return `${Number(value).toFixed(1)}%`;
}

function mergeRows(prev, incoming) {
  const ids = new Set(prev.map((r) => r.id));
  const merged = [...prev];
  for (const row of incoming) {
    if (!ids.has(row.id)) {
      merged.push(row);
    }
  }
  return merged;
}

function buildParams(filters, page) {
  const params = {
    city: filters.city ?? "",
    sort: filters.sort ?? "opportunity_score",
    hide_ignored: filters.hide_ignored === false ? "0" : "1",
    page,
  };
  Object.keys(params).forEach((k) => {
    if (params[k] === "" || params[k] == null) delete params[k];
  });
  return params;
}

function readUrlPage() {
  const p = Number(new URLSearchParams(window.location.search).get("page"));
  return Number.isFinite(p) && p > 0 ? p : 1;
}

function pageRange(page, total, lastPage) {
  const p = Math.min(lastPage, Math.max(1, page));
  return {
    page: p,
    from: (p - 1) * PER_PAGE + 1,
    to: Math.min(p * PER_PAGE, total),
  };
}

function syncUrlPage(filters, page) {
  const params = new URLSearchParams(buildParams(filters, page));
  const qs = params.toString();
  const next = `${window.location.pathname}${qs ? `?${qs}` : ""}`;
  if (window.location.pathname + window.location.search !== next) {
    window.history.replaceState(window.history.state, "", next);
  }
}

export default function NichesIndex({
  scans: initialScans,
  pagination,
  cities,
  filters,
  nicheCatalog = [],
  ignoredCount = 0,
}) {
  const { flash } = usePage().props;
  const [toast, setToast] = useState(null);
  const [rows, setRows] = useState(initialScans);
  const [meta, setMeta] = useState(pagination);
  const [loadingMore, setLoadingMore] = useState(false);
  const [selected, setSelected] = useState(null);
  const [manageOpen, setManageOpen] = useState(false);
  const [annotateScan, setAnnotateScan] = useState(null);
  const [viewRange, setViewRange] = useState({
    from: 1,
    to: Math.min(PER_PAGE, pagination?.total ?? PER_PAGE),
    page: 1,
  });
  const sentinelRef = useRef(null);
  const metaBarRef = useRef(null);
  const tableScrollRef = useRef(null);
  const tableWrapRef = useRef(null);
  const hydratingRef = useRef(false);
  const deepLinkDoneRef = useRef(false);
  const scrollRafRef = useRef(null);
  const [urlRevision, setUrlRevision] = useState(0);
  const filterKey = `${filters?.city ?? ""}|${filters?.sort ?? "opportunity_score"}|${filters?.hide_ignored === false ? "0" : "1"}`;

  const total = meta?.total ?? 0;
  const lastPage = meta?.last_page ?? 1;

  useEffect(() => {
    if (flash?.success) {
      setToast(flash.success);
    }
  }, [flash?.success]);

  useEffect(() => {
    if (flash?.error) {
      setToast(flash.error);
    }
  }, [flash?.error]);

  useEffect(() => {
    setMeta(pagination);
  }, [pagination]);

  // Reset list only when filters change — not on infinite-scroll partial reloads (those merge in loadPage).
  useEffect(() => {
    setRows(initialScans);
    setPendingKeys(
      initialScans.filter((row) => row.is_pending).map(nicheCityKey),
    );
    setSelected(null);
    deepLinkDoneRef.current = false;
    const initialTo = Math.min(
      PER_PAGE,
      pagination?.total ?? initialScans.length,
    );
    setViewRange({
      from: initialScans.length ? 1 : 0,
      to: initialTo || 0,
      page: 1,
    });
  }, [filterKey]);

  useEffect(() => {
    const el = tableScrollRef.current;
    if (!el) {
      return undefined;
    }

    const setTableOffset = () => {
      const top = el.getBoundingClientRect().top;
      document.documentElement.style.setProperty(
        "--niches-table-offset",
        `${Math.ceil(top)}px`,
      );
    };

    setTableOffset();
    const ro = new ResizeObserver(setTableOffset);
    ro.observe(el);
    window.addEventListener("resize", setTableOffset);
    document.addEventListener("scroll", setTableOffset, {
      passive: true,
      capture: true,
    });
    return () => {
      ro.disconnect();
      window.removeEventListener("resize", setTableOffset);
      document.removeEventListener("scroll", setTableOffset, { capture: true });
    };
  }, [rows.length, selected]);

  const getScrollAnchor = useCallback(() => {
    const thead = tableWrapRef.current?.querySelector("thead");
    if (thead) {
      return thead.getBoundingClientRect().bottom;
    }
    const metaBottom =
      metaBarRef.current?.getBoundingClientRect().bottom ?? TOPBAR_HEIGHT + 37;
    return metaBottom;
  }, []);

  const applyPageRange = useCallback(
    (page) => {
      const next = pageRange(page, total, lastPage);
      setViewRange((prev) => {
        if (
          prev.page === next.page &&
          prev.from === next.from &&
          prev.to === next.to
        ) {
          return prev;
        }
        return next;
      });
      syncUrlPage(filters, next.page);
    },
    [filters, lastPage, total],
  );

  const updateViewRangeFromScroll = useCallback(() => {
    const rowEls = tableWrapRef.current?.querySelectorAll(
      "tbody tr[data-niche-row-index]",
    );
    if (!rowEls?.length || total === 0) {
      return;
    }

    const anchor = getScrollAnchor();
    let topIndex = 0;

    for (let i = 0; i < rowEls.length; i++) {
      const rect = rowEls[i].getBoundingClientRect();
      if (rect.bottom > anchor) {
        topIndex = i;
        break;
      }
      topIndex = i;
    }

    const page = Math.floor(topIndex / PER_PAGE) + 1;
    applyPageRange(page);
  }, [applyPageRange, getScrollAnchor, total]);

  const scheduleScrollUpdate = useCallback(() => {
    if (scrollRafRef.current) {
      cancelAnimationFrame(scrollRafRef.current);
    }
    scrollRafRef.current = requestAnimationFrame(updateViewRangeFromScroll);
  }, [updateViewRangeFromScroll]);

  useEffect(() => {
    scheduleScrollUpdate();

    // Capture phase catches scroll on any element (not only window).
    document.addEventListener("scroll", scheduleScrollUpdate, {
      passive: true,
      capture: true,
    });
    window.addEventListener("resize", scheduleScrollUpdate);
    window.visualViewport?.addEventListener("resize", scheduleScrollUpdate);
    window.visualViewport?.addEventListener("scroll", scheduleScrollUpdate);

    const rowEls = tableWrapRef.current?.querySelectorAll(
      "tbody tr[data-niche-row-index]",
    );
    const rowObserver =
      rowEls?.length &&
      new IntersectionObserver(() => scheduleScrollUpdate(), {
        root: null,
        threshold: [0, 0.01, 0.25, 0.5, 1],
      });

    if (rowObserver) {
      rowEls.forEach((row) => rowObserver.observe(row));
    }

    const removeRouterFinish = router.on("finish", () => {
      setUrlRevision((r) => r + 1);
      scheduleScrollUpdate();
    });

    return () => {
      document.removeEventListener("scroll", scheduleScrollUpdate, {
        capture: true,
      });
      window.removeEventListener("resize", scheduleScrollUpdate);
      window.visualViewport?.removeEventListener(
        "resize",
        scheduleScrollUpdate,
      );
      window.visualViewport?.removeEventListener(
        "scroll",
        scheduleScrollUpdate,
      );
      rowObserver?.disconnect();
      removeRouterFinish();
      if (scrollRafRef.current) {
        cancelAnimationFrame(scrollRafRef.current);
      }
    };
  }, [rows.length, scheduleScrollUpdate]);

  const applyFilters = (overrides = {}) => {
    setSelected(null);
    const params = buildParams({ ...filters, ...overrides }, 1);
    router.get("/niches", params, { preserveState: true });
  };

  const [scanningRowId, setScanningRowId] = useState(null);
  const [refreshQueuingRowId, setRefreshQueuingRowId] = useState(null);
  const [pendingKeys, setPendingKeys] = useState([]);

  const isComboPending = useCallback(
    (row) => row.is_pending || pendingKeys.includes(nicheCityKey(row)),
    [pendingKeys],
  );

  const patchRowFromStatus = useCallback((comboKey, data) => {
    const patch = {
      id: data.id,
      result_count: data.result_count,
      sampled_count: data.sampled_count,
      avg_gbp_score: data.avg_gbp_score,
      pct_no_website: data.pct_no_website,
      pct_low_reviews: data.pct_low_reviews,
      opportunity_score: data.opportunity_score,
      status: data.status,
      is_pending: data.is_pending,
      ran_at_human: data.ran_at_human,
    };

    setRows((prev) =>
      prev.map((row) =>
        nicheCityKey(row) === comboKey ? { ...row, ...patch } : row,
      ),
    );

    setSelected((prev) =>
      prev && nicheCityKey(prev) === comboKey ? { ...prev, ...patch } : prev,
    );

    if (
      !data.is_pending &&
      (data.status === "complete" || data.status === "failed")
    ) {
      setPendingKeys((prev) => prev.filter((key) => key !== comboKey));
    } else if (data.is_pending) {
      setPendingKeys((prev) =>
        prev.includes(comboKey) ? prev : [...prev, comboKey],
      );
    }
  }, []);

  useNicheScanStatusPoll(pendingKeys, rows, patchRowFromStatus);

  const refreshScan = (row) => {
    if (refreshQueuingRowId || isComboPending(row)) {
      return;
    }

    setRefreshQueuingRowId(row.id);
    router.post(
      `/niches/${row.id}/refresh`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          setPendingKeys((prev) =>
            prev.includes(nicheCityKey(row))
              ? prev
              : [...prev, nicheCityKey(row)],
          );
        },
        onFinish: () => setRefreshQueuingRowId(null),
      },
    );
  };

  const refreshScanLabel = (row) => {
    if (refreshQueuingRowId === row.id) {
      return "Queuing…";
    }
    if (isComboPending(row)) {
      return "Scan in progress…";
    }
    return "Refresh scan";
  };

  const runFullScan = (row) => {
    if (scanningRowId) {
      return;
    }

    setScanningRowId(row.id);
    router.post(
      "/searches",
      {
        niche: row.niche_query,
        city: row.city,
        country: row.country,
        scan_type: "gbp_only",
      },
      {
        onFinish: () => setScanningRowId(null),
      },
    );
  };

  const loadPage = useCallback(
    (nextPage, { replace = true, onDone, force = false } = {}) => {
      if (!force && (loadingMore || nextPage > meta.last_page)) {
        return;
      }

      setLoadingMore(true);
      router.get("/niches", buildParams(filters, nextPage), {
        preserveState: true,
        preserveScroll: true,
        replace,
        only: ["scans", "pagination"],
        onSuccess: ({ props }) => {
          setRows((prev) => mergeRows(prev, props.scans));
          setMeta(props.pagination);
        },
        onFinish: () => {
          setLoadingMore(false);
          onDone?.();
          scheduleScrollUpdate();
        },
      });
    },
    [filters, loadingMore, meta.last_page, scheduleScrollUpdate],
  );

  useEffect(() => {
    if (deepLinkDoneRef.current || hydratingRef.current) {
      return;
    }

    const urlPage = Number(
      new URLSearchParams(window.location.search).get("page") ||
        filters.page ||
        1,
    );
    if (urlPage <= 1 || meta.last_page < urlPage) {
      deepLinkDoneRef.current = true;
      return;
    }

    if (meta.current_page >= urlPage) {
      deepLinkDoneRef.current = true;
      const target = document.querySelector(
        `[data-niche-row-index="${(urlPage - 1) * PER_PAGE}"]`,
      );
      target?.scrollIntoView({ block: "start" });
      scheduleScrollUpdate();
      return;
    }

    hydratingRef.current = true;
    let page = meta.current_page + 1;

    const loadNext = () => {
      if (page > urlPage) {
        hydratingRef.current = false;
        deepLinkDoneRef.current = true;
        const target = document.querySelector(
          `[data-niche-row-index="${(urlPage - 1) * PER_PAGE}"]`,
        );
        target?.scrollIntoView({ block: "start" });
        scheduleScrollUpdate();
        return;
      }

      loadPage(page, {
        replace: page < urlPage,
        force: true,
        onDone: () => {
          page += 1;
          loadNext();
        },
      });
    };

    loadNext();
  }, [
    filters.page,
    loadPage,
    meta.current_page,
    meta.last_page,
    scheduleScrollUpdate,
  ]);

  useEffect(() => {
    const el = sentinelRef.current;
    if (!el || loadingMore || meta.current_page >= meta.last_page) {
      return undefined;
    }

    const scrollRoot = tableScrollRef.current;
    const observer = new IntersectionObserver(
      (entries) => {
        if (
          entries[0]?.isIntersecting &&
          !loadingMore &&
          meta.current_page < meta.last_page
        ) {
          loadPage(meta.current_page + 1);
        }
      },
      { root: scrollRoot, rootMargin: "400px 0px" },
    );

    observer.observe(el);
    return () => observer.disconnect();
  }, [loadPage, loadingMore, meta.current_page, meta.last_page]);

  // Re-read URL after Inertia partial reloads (nav updates ?page= before scroll handlers run on some hosts).
  void urlRevision;
  const urlPage = readUrlPage();
  const visiblePage =
    viewRange.page === 1 && urlPage > viewRange.page ? urlPage : viewRange.page;
  const { from, to } = pageRange(visiblePage, total, lastPage);

  const pageHeader = (
    <PageHeader
      eyebrow="Niche opportunity"
      title="Rank markets before you scan."
      sub="Sampled GBP weakness by niche and city. Higher opportunity scores suggest denser prospect potential."
    />
  );

  const filterBar = (
    <FilterBar onSubmit={(e) => e.preventDefault()}>
      <div className="filter-action">
        <Button
          type="button"
          kind="secondary"
          onClick={() => setManageOpen(true)}
        >
          Manage niches{ignoredCount > 0 ? ` (${ignoredCount})` : ""}
        </Button>
      </div>
      <Field label="City">
        <Select
          value={filters.city ?? ""}
          onChange={(e) => applyFilters({ city: e.target.value })}
        >
          <option value="">All cities</option>
          {cities.map((city) => (
            <option key={city} value={city}>
              {city}
            </option>
          ))}
        </Select>
      </Field>
      <Field label="Sort by">
        <Segmented
          value={filters.sort ?? "opportunity_score"}
          onChange={(v) => applyFilters({ sort: v })}
          options={[
            { value: "opportunity_score", label: "Opportunity" },
            { value: "result_count", label: "Result count" },
          ]}
        />
      </Field>
      <Field label="Ignored">
        <Segmented
          value={filters.hide_ignored === false ? "show" : "hide"}
          onChange={(v) => applyFilters({ hide_ignored: v === "hide" })}
          options={[
            { value: "hide", label: "Hidden" },
            { value: "show", label: "Shown" },
          ]}
        />
      </Field>
    </FilterBar>
  );

  return (
    <AuthenticatedLayout>
      <Head title="Niches" />

      <main className="page page-wide">
        {rows.length === 0 && !loadingMore ? (
          <>
            {pageHeader}
            {filterBar}
            <EmptyState
              icon={Icons.Search}
              title="No niche scans yet."
              sub='Click "Run Now" to queue a sample scan across default cities and niches.'
            />
          </>
        ) : (
          <div className="niches-layout">
            <div className="niches-main">
              {pageHeader}
              {filterBar}

              <div ref={metaBarRef} className="niches-list-meta">
                Showing {from}–{to} of {total} · Page {visiblePage} of{" "}
                {lastPage}
              </div>

              <div ref={tableScrollRef} className="niches-table-scroll">
                <div ref={tableWrapRef}>
                  <DataTable
                    className="niches-table data-table--flush-top"
                  >
                    <thead>
                      <tr>
                        <th>Niche</th>
                        <th>City</th>
                        <th>Results</th>
                        <th>Avg GBP</th>
                        <th>No website</th>
                        <th>Low reviews</th>
                        <th>Opportunity</th>
                        <th>Last scanned</th>
                        <th className="text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      {rows.map((row, index) => (
                        <tr
                          key={row.id}
                          data-niche-row-index={index}
                          className={selected?.id === row.id ? "selected" : ""}
                          onClick={() => {
                            setAnnotateScan(null);
                            setSelected(row);
                          }}
                        >
                          <td className="biz">
                            {row.niche}
                            {row.is_ignored && (
                              <div className="mt-4">
                                <Status kind="pending">Ignored</Status>
                              </div>
                            )}
                            {(row.status !== "complete" || row.is_pending) && (
                              <div className="mt-4">
                                <Status
                                  kind={
                                    row.status === "failed"
                                      ? "failed"
                                      : "pending"
                                  }
                                >
                                  {row.is_pending ? "pending" : row.status}
                                </Status>
                              </div>
                            )}
                          </td>
                          <td>{row.city}</td>
                          <td className="tabular">{row.result_count ?? "—"}</td>
                          <td>
                            <ScoreBadge
                              value={
                                row.avg_gbp_score != null
                                  ? Math.round(row.avg_gbp_score)
                                  : null
                              }
                              withBar={false}
                            />
                          </td>
                          <td className="tabular">
                            {formatPct(row.pct_no_website)}
                          </td>
                          <td className="tabular">
                            {formatPct(row.pct_low_reviews)}
                          </td>
                          <td>
                            <ScoreBadge
                              value={
                                row.opportunity_score != null
                                  ? Math.round(row.opportunity_score)
                                  : null
                              }
                              withBar={false}
                            />
                          </td>
                          <td className="micro">{row.ran_at_human}</td>
                          <td className="text-right">
                            <RowActions>
                              <Button
                                kind="ghost"
                                size="xs"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  setSelected(null);
                                  setAnnotateScan(row);
                                }}
                              >
                                Annotate
                              </Button>
                              <Button
                                kind="ghost"
                                size="xs"
                                disabled={
                                  refreshQueuingRowId === row.id ||
                                  isComboPending(row)
                                }
                                onClick={(e) => {
                                  e.stopPropagation();
                                  refreshScan(row);
                                }}
                              >
                                {refreshScanLabel(row)}
                              </Button>
                              <Button
                                kind="ghost"
                                size="xs"
                                disabled={scanningRowId === row.id}
                                onClick={(e) => {
                                  e.stopPropagation();
                                  runFullScan(row);
                                }}
                              >
                                {scanningRowId === row.id
                                  ? "Queuing…"
                                  : "Run Full Scan"}
                              </Button>
                            </RowActions>
                          </td>
                        </tr>
                      ))}
                      {loadingMore && (
                        <tr>
                          <td
                            colSpan={9}
                            className="micro text-center"
                          >
                            Loading more…
                          </td>
                        </tr>
                      )}
                      <tr ref={sentinelRef}>
                        <td colSpan={9} className="table-sentinel" />
                      </tr>
                    </tbody>
                  </DataTable>
                </div>
              </div>
            </div>

            {selected && !manageOpen && !annotateScan && (
              <NicheSamplePanel
                scan={selected}
                scanning={scanningRowId === selected.id}
                scanPending={isComboPending(selected)}
                refreshing={refreshQueuingRowId === selected.id}
                onClose={() => setSelected(null)}
                onRefreshScan={refreshScan}
                onRunFullScan={runFullScan}
              />
            )}

            {annotateScan && !manageOpen && (
              <NicheAnnotatePanel
                scan={annotateScan}
                onClose={() => setAnnotateScan(null)}
              />
            )}

            {manageOpen && (
              <ManageNichesPanel
                catalog={nicheCatalog}
                ignoredCount={ignoredCount}
                onClose={() => setManageOpen(false)}
              />
            )}
          </div>
        )}

        {toast && (
          <Toast duration={2200} onClose={() => setToast(null)}>
            {toast}
          </Toast>
        )}
      </main>
    </AuthenticatedLayout>
  );
}

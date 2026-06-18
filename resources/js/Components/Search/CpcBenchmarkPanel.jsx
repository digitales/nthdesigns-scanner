import { useMemo, useRef, useState } from 'react';
import { Button, Field, Input, Status, Textarea } from '@/Components/ui';

const KEYWORD_PREVIEW_LIMIT = 5;

export default function CpcBenchmarkPanel({
    niche,
    city,
    cpcSource,
    cpcSourceLabel,
    cpcBenchmark,
    cpcKeywords,
    marketCpcDefault,
    marketDefaultUpdatedAt,
    googleAdsCpcAvailable,
    fetchingCpc,
    importingCpc,
    processing,
    flash,
    readOnly = false,
    onBenchmarkChange,
    onKeywordsChange,
    onSubmit,
    onFetchFromGoogleAds,
    onImportKeywordPlanner,
}) {
    const fileInputRef = useRef(null);
    const [keywordsExpanded, setKeywordsExpanded] = useState(false);
    const fromGoogleAds = cpcSource === 'google_ads';
    const fromKeywordPlanner = cpcSource === 'keyword_planner_csv';
    const headerHint = readOnly
        ? 'CPC benchmark captured at share time'
        : fromGoogleAds
            ? 'Fetched from Google Ads · saved as default for this niche and city'
            : fromKeywordPlanner
                ? 'Imported from Keyword Planner · saved as default for this niche and city'
                : 'Used in GBP outreach · saved as default for this niche and city';

    const handleImportClick = () => {
        fileInputRef.current?.click();
    };

    const handleFileChange = (event) => {
        const file = event.target.files?.[0];

        if (file) {
            onImportKeywordPlanner(file);
        }

        event.target.value = '';
    };

    const keywordList = useMemo(() => {
        if (typeof cpcKeywords === 'string') {
            return cpcKeywords.split('\n').map((keyword) => keyword.trim()).filter(Boolean);
        }

        return (cpcKeywords ?? []).map((keyword) => String(keyword).trim()).filter(Boolean);
    }, [cpcKeywords]);
    const hasMoreKeywords = keywordList.length > KEYWORD_PREVIEW_LIMIT;
    const visibleKeywords = readOnly && !keywordsExpanded && hasMoreKeywords
        ? keywordList.slice(0, KEYWORD_PREVIEW_LIMIT)
        : keywordList;
    const keywordsText = visibleKeywords.join('\n');
    const displayCpc = cpcBenchmark !== '' && cpcBenchmark != null
        ? cpcBenchmark
        : (marketCpcDefault?.cpc_benchmark ?? null);
    const Wrapper = readOnly ? 'div' : 'form';

    return (
        <Wrapper className="cpc-benchmark-panel" onSubmit={readOnly ? undefined : onSubmit}>
            <header className="cpc-benchmark-panel__header">
                <div>
                    <p className="cpc-benchmark-panel__title">CPC benchmark</p>
                    <h2 className="cpc-benchmark-panel__market">
                        {niche} · {city}
                    </h2>
                    <p className="cpc-benchmark-panel__desc">{headerHint}</p>
                </div>
                {cpcSource && (
                    <Status kind={fromGoogleAds || fromKeywordPlanner ? 'warm' : 'ready'}>{cpcSourceLabel}</Status>
                )}
            </header>

            <div className="cpc-benchmark-panel__body">
                <div className="cpc-benchmark-panel__cpc">
                    <Field label="Cost per click">
                        {readOnly ? (
                            <div className="tabular text-medium">
                                {displayCpc != null ? `£${displayCpc}` : '—'}
                            </div>
                        ) : (
                            <div className="cpc-benchmark-panel__input-row">
                                <div className="input-with-prefix input-with-prefix--narrow">
                                    <span className="prefix">£</span>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={cpcBenchmark}
                                        onChange={(e) => onBenchmarkChange(e.target.value)}
                                        placeholder={marketCpcDefault?.cpc_benchmark ?? '8.50'}
                                    />
                                </div>
                                <div className="cpc-benchmark-panel__actions">
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept=".csv"
                                        hidden
                                        onChange={handleFileChange}
                                    />
                                    <Button
                                        kind="ghost"
                                        size="sm"
                                        type="button"
                                        disabled={importingCpc}
                                        onClick={handleImportClick}
                                    >
                                        {importingCpc ? 'Importing…' : 'Import from Keyword Planner'}
                                    </Button>
                                    {googleAdsCpcAvailable && (
                                        <Button
                                            kind="ghost"
                                            size="sm"
                                            type="button"
                                            disabled={fetchingCpc}
                                            onClick={onFetchFromGoogleAds}
                                        >
                                            {fetchingCpc ? 'Fetching…' : 'Fetch from Google Ads'}
                                        </Button>
                                    )}
                                    <Button
                                        kind="secondary"
                                        size="sm"
                                        type="submit"
                                        disabled={processing}
                                    >
                                        {processing ? 'Saving…' : 'Save default'}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Field>
                </div>

                <Field
                    label="Seed keywords"
                    hint={readOnly ? undefined : 'One per line · from Keyword Planner export or manual entry'}
                >
                    {readOnly ? (
                        <>
                            <div className="micro whitespace-pre-wrap">
                                {keywordsText.trim() ? keywordsText : '—'}
                            </div>
                            {hasMoreKeywords && (
                                <button
                                    type="button"
                                    className="cpc-benchmark-panel__expand"
                                    onClick={() => setKeywordsExpanded((expanded) => !expanded)}
                                >
                                    {keywordsExpanded
                                        ? 'Show fewer'
                                        : `Show all ${keywordList.length} keywords`}
                                </button>
                            )}
                        </>
                    ) : (
                        <Textarea
                            rows={3}
                            value={cpcKeywords}
                            onChange={(e) => onKeywordsChange(e.target.value)}
                            placeholder={'dental practice Birmingham\ndentist Birmingham'}
                        />
                    )}
                </Field>
            </div>

            {!readOnly && (marketDefaultUpdatedAt || flash?.success || flash?.error) && (
                <footer className="cpc-benchmark-panel__footer">
                    {marketDefaultUpdatedAt && (
                        <span>
                            Market default updated{' '}
                            {new Date(marketDefaultUpdatedAt).toLocaleDateString('en-GB')}
                        </span>
                    )}
                    {flash?.success && (
                        <span className="cpc-benchmark-panel__flash cpc-benchmark-panel__flash--success">
                            {flash.success}
                        </span>
                    )}
                    {flash?.error && (
                        <span className="cpc-benchmark-panel__flash cpc-benchmark-panel__flash--error">
                            {flash.error}
                        </span>
                    )}
                </footer>
            )}
        </Wrapper>
    );
}

import { useRef } from 'react';
import { Button, Field, Input, Status, Textarea } from '@/Components/ui';

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
    onBenchmarkChange,
    onKeywordsChange,
    onSubmit,
    onFetchFromGoogleAds,
    onImportKeywordPlanner,
}) {
    const fileInputRef = useRef(null);
    const fromGoogleAds = cpcSource === 'google_ads';
    const fromKeywordPlanner = cpcSource === 'keyword_planner_csv';
    const headerHint = fromGoogleAds
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

    return (
        <form onSubmit={onSubmit} className="cpc-benchmark-panel">
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
                    </Field>
                </div>

                <Field
                    label="Seed keywords"
                    hint="One per line · from Keyword Planner export or manual entry"
                >
                    <Textarea
                        rows={3}
                        value={cpcKeywords}
                        onChange={(e) => onKeywordsChange(e.target.value)}
                        placeholder={'dental practice Birmingham\ndentist Birmingham'}
                    />
                </Field>
            </div>

            {(marketDefaultUpdatedAt || flash?.success || flash?.error) && (
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
        </form>
    );
}

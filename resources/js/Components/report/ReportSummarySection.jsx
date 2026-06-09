import { SevChip } from '@/Components/ui';
import { gradeColor } from '@/Components/ui/scoreBand';

const RISK_LABELS = {
    high: 'High risk',
    moderate: 'Needs work',
    low: 'On track',
};

export default function ReportSummarySection({
    reportContext,
    grade,
    gradeLabel,
    combinedScore,
    violationSummary,
    city,
}) {
    if (!reportContext) {
        return (
            <LegacySummary
                grade={grade}
                gradeLabel={gradeLabel}
                combinedScore={combinedScore}
                violationSummary={violationSummary}
                city={city}
            />
        );
    }

    const color = gradeColor(combinedScore ?? 0);
    const summary = violationSummary ?? {};

    return (
        <>
            <div className="eyebrow eyebrow--spaced">Key finding</div>
            <p className="public-report-headline">{reportContext.headline}</p>

            <div className="public-report-grade-grid public-report-grade-grid--context">
                <div>
                    <div className="public-report-grade-letter" style={{ color }}>
                        {grade}
                    </div>
                    <div className="micro public-report-grade-label">
                        {gradeLabel}
                    </div>
                </div>
                <div>
                    <p className="public-report-lede">
                        We audited your website and Google Business Profile against WCAG 2.2 and local competitors in {city}.
                        {summary.total > 0 && (
                            <> The audit found <strong>{summary.total} issues</strong> worth addressing.</>
                        )}
                    </p>
                    {(reportContext.severity_labels?.length ?? 0) > 0 ? (
                        <div className="public-report-chips">
                            {reportContext.severity_labels.map(({ level, label }) => (
                                <SevChip key={level} level={level} label={label} />
                            ))}
                        </div>
                    ) : (
                        <LegacyChips summary={summary} />
                    )}
                </div>
            </div>

            {(reportContext.dimensions?.length ?? 0) > 0 && (
                <>
                    <div className="eyebrow eyebrow--spaced public-report-dimensions-eyebrow">What we found</div>
                    <ul className="public-report-dimensions">
                        {reportContext.dimensions.map((dimension) => (
                            <li
                                key={dimension.key}
                                className={`public-report-dimension public-report-dimension--${dimension.risk}`}
                            >
                                <strong>{dimension.title}</strong>
                                <span className="public-report-dimension-sep"> · </span>
                                <span>{RISK_LABELS[dimension.risk] ?? dimension.risk}</span>
                                <span className="public-report-dimension-sep"> — </span>
                                <span>{dimension.summary}</span>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </>
    );
}

function LegacyChips({ summary }) {
    return (
        <div className="public-report-chips">
            {summary.critical > 0 && <SevChip level="critical" count={summary.critical} />}
            {summary.serious > 0 && <SevChip level="serious" count={summary.serious} />}
            {summary.moderate > 0 && <SevChip level="moderate" count={summary.moderate} />}
        </div>
    );
}

function LegacySummary({ grade, gradeLabel, combinedScore, violationSummary, city }) {
    const color = gradeColor(combinedScore ?? 0);
    const summary = violationSummary ?? {};

    return (
        <>
            <div className="eyebrow eyebrow--spaced">Overall grade</div>
            <div className="public-report-grade-grid">
                <div>
                    <div className="public-report-grade-letter" style={{ color }}>
                        {grade}
                    </div>
                    <div className="micro public-report-grade-label">
                        {gradeLabel}
                    </div>
                </div>
                <div>
                    <p className="public-report-lede">
                        We audited your website and Google Business Profile against WCAG 2.2 and local competitors in {city}.
                        {summary.total > 0 && (
                            <> The audit found <strong>{summary.total} issues</strong> worth addressing.</>
                        )}
                    </p>
                    <LegacyChips summary={summary} />
                </div>
            </div>
        </>
    );
}

import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Button, Eyebrow, Stack } from '@/Components/ui';

const STATUS_LABELS = {
    matched: 'Matched',
    no_match: 'No match',
    dissolved: 'Dissolved',
    caution: 'Caution',
};

const CALLOUT_CLASS = {
    matched: 'companies-house-callout--matched',
    caution: 'companies-house-callout--caution',
    no_match: 'companies-house-callout--neutral',
    dissolved: 'companies-house-callout--dissolved',
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function companiesHouseFlagTone(flag) {
    const lower = flag.toLowerCase();

    if (
        lower.includes('exclude')
        || lower.includes('dissolved')
        || lower.includes('overdue')
        || lower.includes('not found')
        || lower.includes('not configured')
    ) {
        return 'negative';
    }

    if (lower.includes('active company')) {
        return 'positive';
    }

    return 'neutral';
}

function companiesHouseProfileUrl(number) {
    return `https://find-and-update.company-information.service.gov.uk/company/${number}`;
}

export async function requestCompaniesHouseCheck(prospectId) {
    const res = await fetch(`/prospects/${prospectId}/companies-house/check`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!res.ok && res.status !== 202) {
        throw new Error('Companies House check request failed');
    }

    return res.json();
}

export async function requestCompaniesHouseDetails(prospectId) {
    const res = await fetch(`/prospects/${prospectId}/companies-house/details`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!res.ok && res.status !== 202) {
        throw new Error('Companies House details request failed');
    }

    return res.json();
}

const FINANCIAL_STATUS_MESSAGES = {
    not_disclosed: 'Accounts filed — turnover and profit not disclosed.',
    paper_filed: 'Latest accounts paper-filed — figures not machine-readable.',
    parse_failed: 'Could not extract figures — view the document on Companies House.',
    unavailable: 'No filed accounts found.',
};

const DETAILS_ELIGIBLE_STATUSES = new Set(['matched', 'caution']);

function formatCompaniesHouseMoney(amount) {
    if (amount == null) {
        return null;
    }

    if (amount >= 1_000_000) {
        const millions = amount / 1_000_000;
        const formatted = millions >= 10
            ? Math.round(millions)
            : Number(millions.toFixed(1));

        return `£${formatted}m`;
    }

    if (amount >= 100_000) {
        return `£${Math.round(amount / 1_000)}k`;
    }

    return `£${amount.toLocaleString()}`;
}

function formatCompaniesHouseDate(value) {
    if (!value) {
        return null;
    }

    return new Date(value).toLocaleDateString();
}

function CompaniesHouseFlags({ flags }) {
    if (flags.length === 0) {
        return null;
    }

    return (
        <div className="companies-house-flags" role="list">
            {flags.map((flag) => {
                const tone = companiesHouseFlagTone(flag);

                return (
                    <span
                        key={flag}
                        role="listitem"
                        className={`qualification-flag qualification-flag--${tone}`}
                    >
                        <span className="qualification-flag-mark" aria-hidden="true" />
                        {flag}
                    </span>
                );
            })}
        </div>
    );
}

function RegistrationMeta({ byName, at }) {
    if (!at) {
        return null;
    }

    return (
        <p className="micro text-stone companies-house-meta">
            Registered
            {byName ? ` by ${byName}` : ''}
            {' '}
            <time dateTime={at}>{new Date(at).toLocaleString()}</time>
        </p>
    );
}

function CompaniesHouseSnapshot({ snapshot }) {
    if (!snapshot) {
        return null;
    }

    return (
        <div className="companies-house-panel">
            <h4 className="companies-house-panel-title">Snapshot</h4>
            <dl className="companies-house-dl companies-house-dl--snapshot">
                {snapshot.company_type && (
                    <>
                        <dt>Type</dt>
                        <dd>{snapshot.company_type.replace(/-/g, ' ')}</dd>
                    </>
                )}
                {snapshot.sic_codes?.length > 0 && (
                    <>
                        <dt>SIC</dt>
                        <dd>{snapshot.sic_codes.join(', ')}</dd>
                    </>
                )}
                {snapshot.registered_office && (
                    <>
                        <dt>Registered office</dt>
                        <dd>{snapshot.registered_office}</dd>
                    </>
                )}
                {snapshot.incorporated_on && (
                    <>
                        <dt>Incorporated</dt>
                        <dd>{formatCompaniesHouseDate(snapshot.incorporated_on)}</dd>
                    </>
                )}
                {snapshot.accounts?.next_due && (
                    <>
                        <dt>Accounts due</dt>
                        <dd>
                            {formatCompaniesHouseDate(snapshot.accounts.next_due)}
                            {snapshot.accounts.overdue ? ' (overdue)' : ''}
                        </dd>
                    </>
                )}
                {snapshot.accounts?.last_made_up_to && (
                    <>
                        <dt>Last accounts to</dt>
                        <dd>{formatCompaniesHouseDate(snapshot.accounts.last_made_up_to)}</dd>
                    </>
                )}
            </dl>
        </div>
    );
}

function CompaniesHouseFinancials({ financials, documentLink }) {
    if (!financials) {
        return null;
    }

    const isAvailable = financials.status === 'available';

    return (
        <div className="companies-house-panel">
            <h4 className="companies-house-panel-title">Latest accounts</h4>
            {isAvailable ? (
                <>
                    <dl className="companies-house-dl companies-house-dl--financials">
                        {financials.turnover != null && (
                            <>
                                <dt>Turnover</dt>
                                <dd>{formatCompaniesHouseMoney(financials.turnover)}</dd>
                            </>
                        )}
                        {financials.profit_before_tax != null && (
                            <>
                                <dt>Profit before tax</dt>
                                <dd>{formatCompaniesHouseMoney(financials.profit_before_tax)}</dd>
                            </>
                        )}
                        {financials.net_assets != null && (
                            <>
                                <dt>Net assets</dt>
                                <dd>{formatCompaniesHouseMoney(financials.net_assets)}</dd>
                            </>
                        )}
                        {financials.employees != null && (
                            <>
                                <dt>Employees</dt>
                                <dd>{financials.employees}</dd>
                            </>
                        )}
                    </dl>
                    {financials.period_end && (
                        <p className="micro text-stone companies-house-panel-meta">
                            Period to {formatCompaniesHouseDate(financials.period_end)}
                            {financials.filing_date ? ` · Filed ${formatCompaniesHouseDate(financials.filing_date)}` : ''}
                        </p>
                    )}
                </>
            ) : (
                <p className="micro text-stone companies-house-panel-copy">
                    {FINANCIAL_STATUS_MESSAGES[financials.status] ?? 'Financial figures unavailable.'}
                </p>
            )}
            {documentLink && (
                <a
                    href={documentLink}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="micro companies-house-external-link companies-house-panel-link"
                >
                    View accounts document
                </a>
            )}
        </div>
    );
}

function CompaniesHouseActivity({ activity, filingHistoryLink }) {
    const items = (activity ?? []).slice(0, 8);

    if (items.length === 0) {
        return null;
    }

    return (
        <div className="companies-house-panel">
            <h4 className="companies-house-panel-title">Recent activity</h4>
            <ul className="companies-house-activity-list">
                {items.map((item) => (
                    <li key={`${item.date}-${item.type}-${item.description}`}>
                        <time dateTime={item.date}>{formatCompaniesHouseDate(item.date)}</time>
                        <span>{item.description}</span>
                    </li>
                ))}
            </ul>
            {filingHistoryLink && (
                <a
                    href={filingHistoryLink}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="micro companies-house-external-link companies-house-panel-link"
                >
                    View full history on Companies House
                </a>
            )}
        </div>
    );
}

function CompaniesHouseOfficers({ officers }) {
    const [expanded, setExpanded] = useState(false);
    const items = officers ?? [];
    const visible = expanded ? items : items.slice(0, 3);

    if (items.length === 0) {
        return null;
    }

    return (
        <div className="companies-house-panel">
            <h4 className="companies-house-panel-title">Officers</h4>
            <ul className="companies-house-officers-list">
                {visible.map((officer) => (
                    <li key={`${officer.name}-${officer.role}-${officer.appointed_on}`}>
                        <span className="companies-house-officer-name">{officer.name}</span>
                        <span className="micro text-stone">
                            {officer.role}
                            {officer.appointed_on ? ` · since ${formatCompaniesHouseDate(officer.appointed_on)}` : ''}
                        </span>
                    </li>
                ))}
            </ul>
            {items.length > 3 && (
                <Button kind="ghost" size="sm" type="button" onClick={() => setExpanded((v) => !v)}>
                    {expanded ? 'Show fewer' : `Show all ${items.length} officers`}
                </Button>
            )}
        </div>
    );
}

function CompaniesHouseTalkingPoints({ points }) {
    if (!points?.length) {
        return null;
    }

    return (
        <div className="companies-house-panel companies-house-panel--hooks">
            <h4 className="companies-house-panel-title">Outreach hooks</h4>
            <ul className="companies-house-hooks-list">
                {points.map((point) => (
                    <li key={point}>{point}</li>
                ))}
            </ul>
        </div>
    );
}

function CompaniesHouseDetailsSection({ prospect, loading, onLoad }) {
    const details = prospect.companies_house_details;
    const hasDetails = Boolean(details && prospect.companies_house_details_loaded_at);

    return (
        <section className="companies-house-section" aria-labelledby="companies-house-details-heading">
            <div className="companies-house-section-head companies-house-section-head--row">
                <div id="companies-house-details-heading">
                    <Eyebrow>Company details</Eyebrow>
                    {prospect.companies_house_details_loaded_at && (
                        <time
                            className="micro text-stone companies-house-checked-at"
                            dateTime={prospect.companies_house_details_loaded_at}
                        >
                            Loaded {new Date(prospect.companies_house_details_loaded_at).toLocaleString()}
                        </time>
                    )}
                </div>
                <Stack direction="row" gap={8} className="companies-house-control-actions">
                    {!hasDetails ? (
                        <Button kind="secondary" size="sm" type="button" onClick={onLoad} disabled={loading}>
                            {loading ? 'Loading details…' : 'Load details'}
                        </Button>
                    ) : (
                        <Button kind="ghost" size="sm" type="button" onClick={onLoad} disabled={loading}>
                            {loading ? 'Refreshing…' : 'Refresh details'}
                        </Button>
                    )}
                </Stack>
            </div>

            {!hasDetails && !loading && (
                <p className="micro text-stone">
                    Load filing history, officers, and financial figures when available.
                </p>
            )}

            {hasDetails && (
                <div className="companies-house-details-grid">
                    <CompaniesHouseSnapshot snapshot={details.company_snapshot} />
                    <CompaniesHouseFinancials
                        financials={details.financials}
                        documentLink={details.links?.latest_accounts_document}
                    />
                    <CompaniesHouseActivity
                        activity={details.recent_activity}
                        filingHistoryLink={details.links?.filing_history}
                    />
                    <CompaniesHouseOfficers officers={details.officers} />
                    <CompaniesHouseTalkingPoints points={details.talking_points} />
                </div>
            )}
        </section>
    );
}

export default function CompaniesHouseControl({ prospect }) {
    const [checking, setChecking] = useState(false);
    const [loadingDetails, setLoadingDetails] = useState(false);

    const hasRegistration = Boolean(
        prospect.registered_company_name || prospect.registered_company_number,
    );
    const wasCleared = Boolean(
        !hasRegistration && prospect.registered_company_cleared_at,
    );
    const [showForm, setShowForm] = useState(false);
    const [confirmClear, setConfirmClear] = useState(false);
    const [form, setForm] = useState({
        name: prospect.registered_company_name ?? '',
        number: prospect.registered_company_number ?? '',
        note: prospect.registered_company_note ?? '',
    });

    const status = prospect.companies_house_status;
    const flags = prospect.companies_house_flags ?? [];
    const tradingName = prospect.business_name?.trim() ?? '';
    const registeredName = prospect.registered_company_name?.trim() ?? '';
    const namesDiffer = hasRegistration
        && tradingName !== ''
        && registeredName !== ''
        && tradingName.toLowerCase() !== registeredName.toLowerCase();

    const openForm = () => {
        setForm({
            name: prospect.registered_company_name ?? '',
            number: prospect.registered_company_number ?? '',
            note: prospect.registered_company_note ?? '',
        });
        setShowForm(true);
    };

    const handleCheck = async () => {
        if (checking) {
            return;
        }

        setChecking(true);

        try {
            await requestCompaniesHouseCheck(prospect.id);
            router.reload({ only: ['prospect'], preserveScroll: true });
        } catch {
            setChecking(false);
        }
    };

    const handleLoadDetails = async () => {
        if (loadingDetails) {
            return;
        }

        setLoadingDetails(true);

        try {
            await requestCompaniesHouseDetails(prospect.id);
            router.reload({ only: ['prospect'], preserveScroll: true });
        } catch {
            setLoadingDetails(false);
        }
    };

    const showDetailsSection = DETAILS_ELIGIBLE_STATUSES.has(status);

    const saveRegistration = (e) => {
        e.preventDefault();
        router.post(`/prospects/${prospect.id}/registered-company`, {
            name: form.name.trim() || null,
            number: form.number.trim() || null,
            note: form.note.trim() || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setShowForm(false);
                setConfirmClear(false);
            },
        });
    };

    const clearRegistration = () => {
        router.delete(`/prospects/${prospect.id}/registered-company`, {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmClear(false);
                setForm({ name: '', number: '', note: '' });
                setShowForm(false);
            },
        });
    };

    return (
        <Stack gap={0} className="companies-house-control">
            <section className="companies-house-section" aria-labelledby="companies-house-entity-heading">
                <div className="companies-house-section-head" id="companies-house-entity-heading">
                    <Eyebrow>Legal entity</Eyebrow>
                    <p className="micro text-stone companies-house-lead">
                        Register the legal name or company number when this business trades under a different name on Google.
                    </p>
                </div>

                {wasCleared && !showForm && (
                    <div className="companies-house-callout companies-house-callout--neutral">
                        <p className="companies-house-summary">
                            Registration cleared
                            {prospect.registered_company_cleared_by_name
                                ? ` by ${prospect.registered_company_cleared_by_name}`
                                : ''}
                            {prospect.registered_company_cleared_at
                                ? ` on ${new Date(prospect.registered_company_cleared_at).toLocaleString()}`
                                : ''}
                            . Previous check results are kept below.
                        </p>
                        <Button kind="ghost" size="sm" type="button" onClick={openForm}>
                            Register again…
                        </Button>
                    </div>
                )}

                {hasRegistration && !showForm && (
                    <div className="companies-house-registration">
                        <dl className="companies-house-dl">
                            {namesDiffer && (
                                <>
                                    <dt>Trading as</dt>
                                    <dd>{tradingName}</dd>
                                </>
                            )}
                            {prospect.registered_company_name && (
                                <>
                                    <dt>Registered name</dt>
                                    <dd>{prospect.registered_company_name}</dd>
                                </>
                            )}
                            {prospect.registered_company_number && (
                                <>
                                    <dt>Company number</dt>
                                    <dd>
                                        <a
                                            href={companiesHouseProfileUrl(prospect.registered_company_number)}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="companies-house-external-link"
                                        >
                                            {prospect.registered_company_number}
                                        </a>
                                    </dd>
                                </>
                            )}
                            {prospect.registered_company_note && (
                                <>
                                    <dt>Note</dt>
                                    <dd className="text-stone">{prospect.registered_company_note}</dd>
                                </>
                            )}
                        </dl>

                        <RegistrationMeta
                            byName={prospect.registered_company_by_name}
                            at={prospect.registered_company_at}
                        />

                        <Stack direction="row" gap={8} className="companies-house-registration-actions">
                            <Button kind="ghost" size="sm" type="button" onClick={openForm}>Edit</Button>
                            {!confirmClear ? (
                                <Button kind="ghost" size="sm" type="button" onClick={() => setConfirmClear(true)}>
                                    Clear
                                </Button>
                            ) : (
                                <>
                                    <Button kind="secondary" size="sm" type="button" onClick={clearRegistration}>
                                        Confirm clear
                                    </Button>
                                    <Button kind="ghost" size="sm" type="button" onClick={() => setConfirmClear(false)}>
                                        Cancel
                                    </Button>
                                </>
                            )}
                        </Stack>
                    </div>
                )}

                {!hasRegistration && !wasCleared && !showForm && (
                    <div className="companies-house-empty">
                        <p className="micro text-stone">
                            No legal entity registered. Checks use the Google Business Profile name
                            {tradingName ? ` (“${tradingName}”)` : ''}.
                        </p>
                        <Button kind="ghost" size="sm" type="button" onClick={openForm}>
                            Register legal entity…
                        </Button>
                    </div>
                )}

                {showForm && (
                    <Stack as="form" gap={10} className="companies-house-form" onSubmit={saveRegistration}>
                        <div className="companies-house-form-grid">
                            <div className="field">
                                <label className="field-label" htmlFor="registered-company-name">
                                    Registered company name
                                </label>
                                <input
                                    id="registered-company-name"
                                    name="name"
                                    type="text"
                                    className="input"
                                    value={form.name}
                                    onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                                    placeholder="Legal entity name"
                                />
                            </div>
                            <div className="field">
                                <label className="field-label" htmlFor="registered-company-number">
                                    Companies House number
                                </label>
                                <input
                                    id="registered-company-number"
                                    name="number"
                                    type="text"
                                    className="input"
                                    value={form.number}
                                    onChange={(e) => setForm((f) => ({ ...f, number: e.target.value }))}
                                    placeholder="8-character number"
                                    maxLength={8}
                                    inputMode="text"
                                    autoCapitalize="characters"
                                />
                            </div>
                        </div>
                        <div className="field">
                            <label className="field-label" htmlFor="registered-company-note">
                                Note
                                <span className="field-hint">Optional</span>
                            </label>
                            <textarea
                                id="registered-company-note"
                                name="note"
                                className="textarea w-full"
                                rows={2}
                                value={form.note}
                                onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))}
                                placeholder="e.g. Found on website footer"
                            />
                        </div>
                        <p className="micro text-stone">Enter at least one of name or number.</p>
                        <Stack direction="row" gap={8} className="companies-house-form-actions">
                            <Button kind="primary" size="sm" type="submit">Save registration</Button>
                            <Button
                                kind="ghost"
                                size="sm"
                                type="button"
                                onClick={() => {
                                    setShowForm(false);
                                    setConfirmClear(false);
                                }}
                            >
                                Cancel
                            </Button>
                        </Stack>
                    </Stack>
                )}
            </section>

            <div className="companies-house-divider" role="presentation" />

            <section className="companies-house-section" aria-labelledby="companies-house-check-heading">
                <div className="companies-house-section-head companies-house-section-head--row">
                    <div id="companies-house-check-heading">
                        <Eyebrow>Registry check</Eyebrow>
                        {prospect.companies_house_checked_at && (
                            <time
                                className="micro text-stone companies-house-checked-at"
                                dateTime={prospect.companies_house_checked_at}
                            >
                                Checked {new Date(prospect.companies_house_checked_at).toLocaleString()}
                            </time>
                        )}
                    </div>
                    <Stack direction="row" gap={8} align="center" className="companies-house-status-row">
                        {status ? (
                            <Badge className={`companies-house-badge companies-house-badge--${status}`}>
                                {STATUS_LABELS[status] ?? status}
                            </Badge>
                        ) : (
                            <span className="micro text-stone">Not checked yet</span>
                        )}
                        {prospect.companies_house_number && (
                            <a
                                href={companiesHouseProfileUrl(prospect.companies_house_number)}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="micro companies-house-external-link"
                            >
                                No. {prospect.companies_house_number}
                            </a>
                        )}
                    </Stack>
                </div>

                {prospect.companies_house_summary ? (
                    <div className={`companies-house-callout ${CALLOUT_CLASS[status] ?? 'companies-house-callout--neutral'}`}>
                        <p className="companies-house-summary">{prospect.companies_house_summary}</p>
                    </div>
                ) : !status ? (
                    <p className="micro text-stone">
                        Run a check to verify company status, filing compliance, and registered charges.
                    </p>
                ) : null}

                <CompaniesHouseFlags flags={flags} />

                <Stack direction="row" gap={8} className="companies-house-control-actions">
                    <Button
                        kind="secondary"
                        size="sm"
                        type="button"
                        onClick={handleCheck}
                        disabled={checking}
                    >
                        {checking ? 'Checking…' : status ? 'Recheck' : 'Check Companies House'}
                    </Button>
                </Stack>
            </section>

            {showDetailsSection && (
                <>
                    <div className="companies-house-divider" role="presentation" />
                    <CompaniesHouseDetailsSection
                        prospect={prospect}
                        loading={loadingDetails}
                        onLoad={handleLoadDetails}
                    />
                </>
            )}
        </Stack>
    );
}

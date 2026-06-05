export function hasA11yAuditContent({ summary, topViolations } = {}) {
    const totals = summary ?? {};

    return (totals.total ?? 0) > 0 || (topViolations?.length ?? 0) > 0;
}

export function hasLighthouseMetrics(lighthouse = {}) {
    return lighthouse.performance != null
        || lighthouse.accessibility != null
        || lighthouse.seo != null
        || lighthouse.best_practices != null;
}

export function shouldShowA11yAudit({
    scanType,
    effectiveScanType,
    auditPending = false,
    auditFailure = null,
} = {}) {
    const effective = effectiveScanType ?? scanType;

    return effective !== 'gbp_only' || auditPending || Boolean(auditFailure);
}

export function showA11yForSearch(scanType) {
    return scanType !== 'gbp_only';
}

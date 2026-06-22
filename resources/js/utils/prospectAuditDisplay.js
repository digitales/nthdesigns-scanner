export function prospectHasAuditIssue(prospect) {
    return Boolean(prospect.audit_service_error || prospect.site_load_error);
}

export function prospectUrlDisplay(prospect) {
    const isFailed = prospect.audit_status === 'failed';
    const isPending = prospect.audit_status === 'pending';

    if (isFailed) {
        return {
            text: prospect.audit_error ?? 'Audit failed',
            critical: true,
            showUrl: false,
        };
    }

    if (prospect.audit_service_error) {
        return {
            text: 'Audit timed out',
            critical: true,
            showUrl: false,
        };
    }

    if (prospect.site_load_error && !isPending) {
        return {
            text: 'Site failed to load',
            critical: true,
            showUrl: false,
        };
    }

    return {
        text: prospect.website_url?.replace(/^https?:\/\//, '') ?? 'No website',
        critical: false,
        showUrl: Boolean(prospect.website_url),
    };
}

export function prospectStatusLabel(prospect) {
    const isFailed = prospect.audit_status === 'failed';
    const isPending = prospect.audit_status === 'pending';
    const isSiteUnreachable = Boolean(prospect.site_unreachable);

    if (isFailed) {
        return {
            kind: 'failed',
            label: isSiteUnreachable ? 'Site unreachable' : 'Audit failed',
        };
    }

    if (isPending) {
        return {
            kind: 'pending',
            label: prospect.progress_flow?.status_message ?? 'Auditing site',
        };
    }

    if (prospect.audit_service_error) {
        return {
            kind: 'failed',
            label: 'Audit timed out',
        };
    }

    if (!prospect.report_ready) {
        return {
            kind: 'pending',
            label: prospect.progress_flow?.status_message ?? 'Generating report',
        };
    }

    if (prospect.is_warm) {
        return {
            kind: 'warm',
            label: `Viewed ${prospect.last_viewed}`,
        };
    }

    return {
        kind: 'ready',
        label: 'Report ready',
    };
}

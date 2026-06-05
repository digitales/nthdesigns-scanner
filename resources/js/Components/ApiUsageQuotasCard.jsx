import { useForm } from '@inertiajs/react';
import { Button, Card, Field, FormError, Input, Stack } from '@/Components/ui';

function formatCost(pence) {
    return `~£${(pence / 100).toFixed(2)}`;
}

function periodLabel(type) {
    return type === 'daily' ? 'Daily' : 'Monthly';
}

function statusMessage(status, type) {
    if (status === 'blocked') {
        return `Blocked — ${type} limit reached`;
    }

    if (status === 'warning') {
        return `Approaching ${type} limit`;
    }

    return null;
}

function QuotaBar({ period, type }) {
    const pct = Math.min(100, period.pct ?? 0);
    const statusClass =
        period.status === 'blocked'
            ? 'api-quota-fill--blocked'
            : period.status === 'warning'
              ? 'api-quota-fill--warning'
              : '';

    return (
        <div className="api-quota-period">
            <div className="api-quota-period-head">
                <span className="micro text-medium">{periodLabel(type)}</span>
                <span className="micro tabular">
                    {period.count} / {period.limit} · {formatCost(period.estimated_cost_pence)}
                </span>
            </div>
            <div className="api-quota-track">
                <div
                    className={`api-quota-fill ${statusClass}`.trim()}
                    style={{ width: `${pct}%` }}
                />
            </div>
            {statusMessage(period.status, type) ? (
                <p className={`micro m-0 api-quota-status api-quota-status--${period.status}`}>
                    {statusMessage(period.status, type)}
                </p>
            ) : null}
        </div>
    );
}

function overrideFieldName(key, periodType) {
    return key.replace('.', '_').concat(`_${periodType}`);
}

export default function ApiUsageQuotasCard({ apiUsage }) {
    const initialOverrides = {};

    for (const operation of apiUsage.operations) {
        initialOverrides[overrideFieldName(operation.key, 'daily')] =
            operation.overrides.daily ?? '';
        initialOverrides[overrideFieldName(operation.key, 'monthly')] =
            operation.overrides.monthly ?? '';
    }

    const form = useForm(initialOverrides);

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => {
            const payload = {};

            for (const [key, value] of Object.entries(data)) {
                payload[key] = value === '' || value === null ? null : Number(value);
            }

            return payload;
        }).patch('/settings/api-quotas');
    };

    return (
        <Card title="API usage & quotas">
            <p className="micro mb-16">
                Google Places and Brave Search consumption. Limits can be lowered below env ceilings in Adjust limits.
            </p>

            <Stack gap={20}>
                {apiUsage.operations.map((operation) => (
                    <div key={operation.key} className="api-quota-group">
                        <p className="micro text-medium m-0 mb-8">{operation.label}</p>
                        <Stack gap={12}>
                            <QuotaBar period={operation.daily} type="daily" />
                            <QuotaBar period={operation.monthly} type="monthly" />
                        </Stack>
                    </div>
                ))}
            </Stack>

            <details className="api-quota-adjust mt-24">
                <summary className="btn btn-secondary btn-sm api-quota-adjust-trigger">
                    Adjust limits
                </summary>
                <form onSubmit={submit} className="mt-16">
                    <Stack gap={16}>
                        {apiUsage.operations.map((operation) => (
                            <div key={`${operation.key}-limits`} className="api-quota-limit-group">
                                <p className="micro text-medium m-0 mb-8">{operation.label}</p>
                                <Stack gap={12}>
                                    {(['daily', 'monthly']).map((periodType) => {
                                        const field = overrideFieldName(operation.key, periodType);
                                        const ceiling = operation.ceilings[periodType];

                                        return (
                                            <Field
                                                key={field}
                                                label={`${periodLabel(periodType)} limit`}
                                                hint={`Env ceiling: ${ceiling}. Leave blank to use default.`}
                                            >
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max={ceiling}
                                                    value={form.data[field]}
                                                    onChange={(e) => form.setData(field, e.target.value)}
                                                />
                                                <FormError message={form.errors[field]} />
                                            </Field>
                                        );
                                    })}
                                </Stack>
                            </div>
                        ))}

                        <div>
                            <Button kind="secondary" type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : 'Save quota limits'}
                            </Button>
                        </div>
                    </Stack>
                </form>
            </details>
        </Card>
    );
}

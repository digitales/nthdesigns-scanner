import { Card, LinkButton, Stack } from '@/Components/ui';

const STEPS = [
    {
        id: 'outreach',
        title: 'Connect outreach mailbox',
        body: 'Your sending domain (e.g. ross@nthdesign.co.uk) with SPF and DKIM configured.',
    },
    {
        id: 'seeds',
        title: 'Add seed accounts',
        body: 'At least two Gmail, Outlook, or Fastmail accounts you control.',
    },
    {
        id: 'start',
        title: 'Start warmup',
        body: 'The engine sends low-volume emails and seeds reply automatically.',
    },
    {
        id: 'ready',
        title: 'Reach ready status',
        body: 'Deliverability score 80+ over 2–4 weeks before cold outreach.',
    },
];

function stepState(stepId, { hasOutreach, seedCount, hasWarming, hasReady }) {
    if (stepId === 'outreach') {
        if (hasOutreach) return 'done';
        return 'active';
    }
    if (stepId === 'seeds') {
        if (seedCount >= 2) return 'done';
        if (hasOutreach) return 'active';
        return 'pending';
    }
    if (stepId === 'start') {
        if (hasReady) return 'done';
        if (hasWarming) return 'done';
        if (hasOutreach && seedCount >= 2) return 'active';
        return 'pending';
    }
    if (stepId === 'ready') {
        if (hasReady) return 'done';
        if (hasWarming) return 'active';
        return 'pending';
    }
    return 'pending';
}

export default function WarmupSetupAside({
    hasOutreach,
    seedCount,
    hasWarming,
    hasReady,
    compact = false,
}) {
    const context = { hasOutreach, seedCount, hasWarming, hasReady };

    return (
        <Stack gap={12}>
            <Card title="Setup checklist">
                <ol className="warmup-setup-list">
                    {STEPS.map((step, index) => {
                        const state = stepState(step.id, context);

                        return (
                            <li
                                key={step.id}
                                className={`warmup-setup-step${state === 'done' ? ' is-done' : ''}${state === 'active' ? ' is-active' : ''}`}
                            >
                                <span className="warmup-setup-step__num" aria-hidden="true">
                                    {state === 'done' ? '✓' : index + 1}
                                </span>
                                <div>
                                    <div className="warmup-setup-step__title">{step.title}</div>
                                    {!compact && (
                                        <p className="warmup-setup-step__body micro text-muted m-0">
                                            {step.body}
                                        </p>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ol>

                {!hasOutreach && (
                    <div className="warmup-aside-cta">
                        <LinkButton href="/warmup/connect">Add outreach mailbox</LinkButton>
                    </div>
                )}
                {hasOutreach && seedCount < 2 && (
                    <div className="warmup-aside-cta">
                        <LinkButton href="/warmup/connect" kind="secondary">
                            Add seed mailbox
                        </LinkButton>
                    </div>
                )}
            </Card>

            <Card title="Monitoring">
                <p className="micro text-muted m-0">
                    Check deliverability every few days. If the score stays flat after a week, verify SPF and DKIM with MXToolbox.
                </p>
            </Card>
        </Stack>
    );
}

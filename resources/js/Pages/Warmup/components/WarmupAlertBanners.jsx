import { Icon, Icons, SkipBanner } from '@/Components/ui';

export default function WarmupAlertBanners({ mailbox, score, alerts = [] }) {
    const showAtRisk =
        mailbox.status === 'at_risk' || (score != null && score < 60 && mailbox.days_warming > 3);

    return (
        <>
            {showAtRisk ? (
                <SkipBanner kind="critical" icon={<Icon d={Icons.Lock} size={14} />}>
                    Deliverability is at risk. Verify SPF, DKIM, and DMARC on your sending domain.{' '}
                    <a
                        href="https://mxtoolbox.com/SuperTool.aspx"
                        target="_blank"
                        rel="noreferrer"
                        className="link-inline"
                    >
                        Check DNS with MXToolbox
                    </a>
                    .
                </SkipBanner>
            ) : null}

            {alerts.length > 0 && !showAtRisk ? (
                <SkipBanner>{alerts[0].message}</SkipBanner>
            ) : null}
        </>
    );
}

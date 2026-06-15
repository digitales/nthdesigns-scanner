import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/Components/ui';
import Arrow from './Arrow';

const PROGRESS_STEPS = [
  { threshold: 'discovering', label: 'Discovered profile on Google Places' },
  { threshold: 'auditing', label: 'Fetched page sample from your site' },
  { threshold: 'auditing', label: 'Running axe-core + Lighthouse' },
  { threshold: 'reporting', label: 'Compiling report' },
];

const PHASE_ORDER = ['queued', 'discovering', 'auditing', 'reporting', 'complete'];

function phaseIndex(phase) {
  const index = PHASE_ORDER.indexOf(phase);
  return index === -1 ? 0 : index;
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export default function AuditWidget({ kind = 'primary', enabled = true }) {
  const [url, setUrl] = useState('');
  const [uiState, setUiState] = useState('idle');
  const [remotePhase, setRemotePhase] = useState('queued');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const pollTokenRef = useRef(null);
  const pollTimerRef = useRef(null);

  const clearPoll = useCallback(() => {
    if (pollTimerRef.current) {
      clearInterval(pollTimerRef.current);
      pollTimerRef.current = null;
    }
  }, []);

  useEffect(() => () => clearPoll(), [clearPoll]);

  const pollStatus = useCallback((token) => {
    clearPoll();

    const checkStatus = async () => {
      try {
        const res = await fetch(`/audit/${token}`, {
          headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
          throw new Error('Could not check audit status.');
        }

        const data = await res.json();
        setRemotePhase(data.failed ? 'failed' : data.phase);
        setMessage(data.message ?? '');

        if (data.report_url) {
          clearPoll();
          window.location.href = data.report_url;
        } else if (data.failed) {
          clearPoll();
          setError(data.message ?? 'We could not complete this audit.');
          setUiState('failed');
        }
      } catch {
        clearPoll();
        setError('Lost connection while checking audit status. Try again.');
        setUiState('failed');
      }
    };

    checkStatus();
    pollTimerRef.current = setInterval(checkStatus, 3000);
  }, [clearPoll]);

  const run = async () => {
    if (!url.trim() || uiState === 'running') {
      return;
    }

    if (!enabled) {
      setError('Audits are not available right now. Please try again later.');
      setUiState('failed');
      return;
    }

    setError('');
    setMessage('Starting your audit…');
    setRemotePhase('queued');
    setUiState('running');

    try {
      const res = await fetch('/audit', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ website_url: url.trim() }),
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        throw new Error(data.message ?? 'Could not start audit.');
      }

      pollTokenRef.current = data.token;
      pollStatus(data.token);
    } catch (err) {
      setError(err.message ?? 'Could not start audit.');
      setUiState('failed');
    }
  };

  const reset = () => {
    clearPoll();
    pollTokenRef.current = null;
    setUiState('idle');
    setRemotePhase('queued');
    setMessage('');
    setError('');
  };

  const currentPhaseIndex = phaseIndex(remotePhase);
  const isRunning = uiState === 'running';

  return (
    <div className="audit-widget">
      <div className="widget-label">Audit your site in 90 seconds</div>
      <div className="audit-row">
        <div className="audit-input">
          <span className="prefix">https://</span>
          <input
            placeholder="your-business.co.uk"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && run()}
            disabled={isRunning}
          />
        </div>
        <Button
          kind={kind === 'accent' ? 'accent' : 'primary'}
          size="lg"
          onClick={uiState === 'failed' ? reset : run}
          disabled={isRunning}
        >
          {isRunning
            ? <><span className="spinner" /> Running audit</>
            : uiState === 'failed'
              ? <>Try again <Arrow /></>
              : <>Run audit <Arrow /></>}
        </Button>
      </div>
      <div className="hint">
        We'll check WCAG 2.2 compliance and Google Business Profile health. No login. No email required to see the result.
      </div>

      {isRunning && (
        <div className="fade-in audit-phase-panel">
          <div className="mono audit-progress-mono">
            {message && <div className="audit-progress-status">{message}</div>}
            {PROGRESS_STEPS.map((step) => {
              const done = currentPhaseIndex >= phaseIndex(step.threshold) && remotePhase !== 'queued';

              return (
                <div key={step.label}>
                  {done ? '✓' : '·'} {step.label}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {uiState === 'failed' && error && (
        <div className="fade-in audit-phase-panel">
          <p className="body-sm text-critical">{error}</p>
        </div>
      )}
    </div>
  );
}

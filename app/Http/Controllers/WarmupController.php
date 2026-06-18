<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWarmupJob;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupMailboxService;
use App\Services\WarmupSeedPoolService;
use App\Services\WarmupSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class WarmupController extends Controller
{
    public function index(WarmupSeedPoolService $poolService): Response
    {
        $user = auth()->user();
        $limits = $user->warmupTierLimits();

        $mailboxes = WarmupMailbox::query()
            ->where('user_id', $user->id)
            ->withCount([
                'sendsFrom as sends_today' => function ($q) {
                    $q->whereDate('sent_at', today());
                },
                'alerts as unread_alerts_count' => function ($q) {
                    $q->whereNull('read_at');
                },
            ])
            ->get()
            ->map(fn (WarmupMailbox $m) => [
                'id' => $m->id,
                'email' => $m->email,
                'provider' => $m->provider,
                'is_outreach_mailbox' => $m->is_outreach_mailbox,
                'is_seed_mailbox' => $m->is_seed_mailbox,
                'is_pool_participant' => $m->is_pool_participant,
                'status' => $m->status,
                'deliverability_score' => $m->deliverability_score,
                'days_warming' => $m->days_warming,
                'sends_today' => $m->sends_today,
                'warmup_enabled' => $m->warmup_enabled,
                'has_alert' => $m->unread_alerts_count > 0,
            ]);

        $seedCount = $mailboxes->where('is_seed_mailbox', true)->count();
        $canStartWarmup = $poolService->canStartWarmup($user);

        return Inertia::render('Warmup/Index', [
            'mailboxes' => $mailboxes,
            'seedCount' => $seedCount,
            'can_start_warmup' => $canStartWarmup,
            'needs_seeds' => ! $canStartWarmup,
            'using_network_only' => $canStartWarmup && $seedCount < 2 && $limits['pool_participation_allowed'],
            'pool' => [
                'active_count' => $poolService->countActivePoolSeeds(),
                'min_size' => config('warmup_pool.min_size'),
                'can_use_pool' => $limits['pool_participation_allowed'],
                'pool_ready' => $poolService->poolReady(),
            ],
            'tier' => [
                'pool_participation_allowed' => $limits['pool_participation_allowed'],
            ],
        ]);
    }

    public function show(WarmupMailbox $mailbox, WarmupSendService $sendService): Response
    {
        abort_unless($mailbox->user_id === auth()->id(), 403);

        $sends = WarmupSend::query()
            ->where('from_mailbox_id', $mailbox->id)
            ->with('toMailbox:id,user_id,email')
            ->orderByDesc('sent_at')
            ->limit(50)
            ->get(['id', 'from_mailbox_id', 'to_mailbox_id', 'subject', 'sent_at', 'status', 'opened_at', 'replied_at', 'rescued_from_spam_at']);

        $weekStart = now()->startOfWeek();

        return Inertia::render('Warmup/Show', [
            'mailbox' => [
                'id' => $mailbox->id,
                'email' => $mailbox->email,
                'provider' => $mailbox->provider,
                'status' => $mailbox->status,
                'deliverability_score' => $mailbox->deliverability_score,
                'days_warming' => $mailbox->days_warming,
                'warmup_enabled' => $mailbox->warmup_enabled,
                'last_imap_check_at' => $mailbox->last_imap_check_at?->toIso8601String(),
            ],
            'sends' => $sends->map(fn (WarmupSend $send) => [
                'id' => $send->id,
                'recipient' => $send->recipientLabel(),
                'subject' => $send->subject,
                'sent_at' => $send->sent_at?->toIso8601String(),
                'status' => $send->status,
                'opened_at' => $send->opened_at?->toIso8601String(),
                'replied_at' => $send->replied_at?->toIso8601String(),
                'rescued_from_spam_at' => $send->rescued_from_spam_at?->toIso8601String(),
            ]),
            'stats' => [
                'sends_this_week' => WarmupSend::query()
                    ->where('from_mailbox_id', $mailbox->id)
                    ->where('sent_at', '>=', $weekStart)
                    ->count(),
                'replies_received' => WarmupSend::query()
                    ->where('from_mailbox_id', $mailbox->id)
                    ->whereNotNull('replied_at')
                    ->where('sent_at', '>=', $weekStart)
                    ->count(),
                'spam_rescues' => WarmupSend::query()
                    ->where('from_mailbox_id', $mailbox->id)
                    ->whereNotNull('rescued_from_spam_at')
                    ->where('sent_at', '>=', $weekStart)
                    ->count(),
            ],
            'estimated_ready_date' => $sendService->getEstimatedReadyDate($mailbox)?->toDateString(),
        ]);
    }

    public function connect(): Response
    {
        $limits = auth()->user()->warmupTierLimits();

        return Inertia::render('Warmup/Connect', [
            'pool_participation_allowed' => $limits['pool_participation_allowed'],
        ]);
    }

    public function store(Request $request, WarmupMailboxService $mailboxService): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'provider' => 'required|in:fastmail,gmail,outlook,generic',
            'imap_host' => 'required|string',
            'imap_port' => 'required|integer',
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer',
            'username' => 'required|string',
            'password' => 'required|string',
            'is_outreach_mailbox' => 'boolean',
            'is_seed_mailbox' => 'boolean',
            'is_pool_participant' => 'boolean',
            'pool_consent_acknowledged' => 'boolean',
            'send_window_start' => 'nullable|date_format:H:i',
            'send_window_end' => 'nullable|date_format:H:i',
            'send_on_weekends' => 'boolean',
        ]);

        $user = $request->user();
        $limits = $user->warmupTierLimits();

        if ($request->boolean('is_outreach_mailbox')) {
            $outreachCount = WarmupMailbox::query()
                ->where('user_id', $user->id)
                ->where('is_outreach_mailbox', true)
                ->count();

            if ($outreachCount >= $limits['max_outreach_mailboxes']) {
                return back()->withErrors([
                    'connection' => 'Your plan allows up to '.$limits['max_outreach_mailboxes'].' outreach mailbox'
                        .($limits['max_outreach_mailboxes'] === 1 ? '' : 'es').'.',
                ]);
            }
        }

        if ($request->boolean('is_seed_mailbox')) {
            $seedCount = WarmupMailbox::query()
                ->where('user_id', $user->id)
                ->where('is_seed_mailbox', true)
                ->count();

            if ($seedCount >= $limits['max_seed_mailboxes']) {
                return back()->withErrors([
                    'connection' => 'Your plan allows up to '.$limits['max_seed_mailboxes'].' seed mailboxes.',
                ]);
            }
        }

        if (! $limits['send_window_customisation_allowed']) {
            $data['send_window_start'] = '08:00:00';
            $data['send_window_end'] = '18:00:00';
        }

        if (! $limits['weekend_volume_control_allowed']) {
            $data['send_on_weekends'] = true;
        }

        if (! $limits['pool_participation_allowed']) {
            $data['is_pool_participant'] = false;
        } elseif ($request->boolean('is_seed_mailbox') && $request->boolean('is_pool_participant', true)) {
            if (! $request->boolean('pool_consent_acknowledged')) {
                return back()->withErrors([
                    'connection' => 'Please acknowledge the shared seed network consent before joining the pool.',
                ]);
            }
            $data['is_pool_participant'] = true;
        } elseif ($request->boolean('is_seed_mailbox')) {
            $data['is_pool_participant'] = false;
        } else {
            $data['is_pool_participant'] = false;
        }

        unset($data['pool_consent_acknowledged']);

        $data['password_encrypted'] = $data['password'];
        $data['user_id'] = $user->id;
        unset($data['password']);

        try {
            $mailboxService->connect($data);
        } catch (RuntimeException $e) {
            return back()->withErrors(['connection' => $e->getMessage()]);
        }

        return redirect()->route('warmup.index')->with('success', 'Mailbox connected.');
    }

    public function testConnection(Request $request, WarmupMailboxService $mailboxService): JsonResponse
    {
        $data = $request->validate([
            'imap_host' => 'required|string',
            'imap_port' => 'required|integer',
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $mailbox = new WarmupMailbox(array_merge($data, [
            'password_encrypted' => $data['password'],
        ]));

        try {
            $mailboxService->verifyConnection($mailbox);

            return response()->json(['imap' => true, 'smtp' => true]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function startWarmup(WarmupMailbox $mailbox, WarmupSeedPoolService $poolService): RedirectResponse
    {
        abort_unless($mailbox->user_id === auth()->id(), 403);

        abort_unless($mailbox->is_outreach_mailbox, 422);

        abort_unless($poolService->canStartWarmup(auth()->user()), 422);

        $mailbox->update([
            'warmup_enabled' => true,
            'warmup_started_at' => now(),
            'status' => 'warming',
        ]);

        ProcessWarmupJob::dispatch($mailbox->id);

        return back()->with('success', 'Warmup started. First sends will go out shortly if you are within your send window.');
    }

    public function togglePause(WarmupMailbox $mailbox): RedirectResponse
    {
        abort_unless($mailbox->user_id === auth()->id(), 403);

        $newStatus = $mailbox->status === 'paused' ? 'warming' : 'paused';
        $mailbox->update(['status' => $newStatus]);

        return back();
    }

    public function togglePoolParticipation(Request $request, WarmupMailbox $mailbox): RedirectResponse
    {
        abort_unless($mailbox->user_id === auth()->id(), 403);
        abort_unless($mailbox->is_seed_mailbox, 422);

        $limits = auth()->user()->warmupTierLimits();
        abort_unless($limits['pool_participation_allowed'], 403);

        $participating = $request->boolean('is_pool_participant');

        if ($participating && ! $request->boolean('pool_consent_acknowledged')) {
            return back()->withErrors([
                'connection' => 'Please acknowledge the shared seed network consent before joining the pool.',
            ]);
        }

        $mailbox->update(['is_pool_participant' => $participating]);

        return back();
    }

    public function destroy(WarmupMailbox $mailbox): RedirectResponse
    {
        abort_unless($mailbox->user_id === auth()->id(), 403);
        $mailbox->delete();

        return redirect()->route('warmup.index');
    }
}

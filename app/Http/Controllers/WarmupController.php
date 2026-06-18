<?php

namespace App\Http\Controllers;

use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupMailboxService;
use App\Services\WarmupSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class WarmupController extends Controller
{
    public function index(): Response
    {
        $mailboxes = WarmupMailbox::query()
            ->where('user_id', auth()->id())
            ->withCount(['sendsFrom as sends_today' => function ($q) {
                $q->whereDate('sent_at', today());
            }])
            ->get()
            ->map(fn (WarmupMailbox $m) => [
                'id' => $m->id,
                'email' => $m->email,
                'provider' => $m->provider,
                'is_outreach_mailbox' => $m->is_outreach_mailbox,
                'is_seed_mailbox' => $m->is_seed_mailbox,
                'status' => $m->status,
                'deliverability_score' => $m->deliverability_score,
                'days_warming' => $m->days_warming,
                'sends_today' => $m->sends_today,
                'warmup_enabled' => $m->warmup_enabled,
            ]);

        $seedCount = $mailboxes->where('is_seed_mailbox', true)->count();

        return Inertia::render('Warmup/Index', [
            'mailboxes' => $mailboxes,
            'seedCount' => $seedCount,
        ]);
    }

    public function show(WarmupMailbox $mailbox, WarmupSendService $sendService): Response
    {
        abort_unless($mailbox->user_id === auth()->id(), 403);

        $sends = WarmupSend::query()
            ->where('from_mailbox_id', $mailbox->id)
            ->orderByDesc('sent_at')
            ->limit(50)
            ->get(['id', 'subject', 'sent_at', 'status', 'opened_at', 'replied_at', 'rescued_from_spam_at']);

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
            'sends' => $sends,
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
        return Inertia::render('Warmup/Connect');
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
        ]);

        $data['password_encrypted'] = $data['password'];
        $data['user_id'] = auth()->id();
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

    public function startWarmup(WarmupMailbox $mailbox): RedirectResponse
    {
        abort_unless($mailbox->user_id === auth()->id(), 403);

        $mailbox->update([
            'warmup_enabled' => true,
            'warmup_started_at' => now(),
            'status' => 'warming',
        ]);

        return back()->with('success', 'Warmup started.');
    }

    public function togglePause(WarmupMailbox $mailbox): RedirectResponse
    {
        abort_unless($mailbox->user_id === auth()->id(), 403);

        $newStatus = $mailbox->status === 'paused' ? 'warming' : 'paused';
        $mailbox->update(['status' => $newStatus]);

        return back();
    }

    public function destroy(WarmupMailbox $mailbox): RedirectResponse
    {
        abort_unless($mailbox->user_id === auth()->id(), 403);
        $mailbox->delete();

        return redirect()->route('warmup.index');
    }
}

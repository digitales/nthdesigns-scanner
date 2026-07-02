<?php

namespace App\Http\Controllers;

use App\Enums\OutreachChannel;
use App\Enums\OutreachSendSource;
use App\Exceptions\WarmupTransportException;
use App\Http\Requests\SendOutreachEmailRequest;
use App\Http\Requests\UpdateOutreachEmailRequest;
use App\Models\OutreachEmail;
use App\Services\Outreach\OutreachSendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OutreachEmailController extends Controller
{
    public function update(UpdateOutreachEmailRequest $request, OutreachEmail $outreachEmail): RedirectResponse
    {
        $this->authorize('update', $outreachEmail);

        if ($outreachEmail->sent_at !== null || $outreachEmail->channel !== OutreachChannel::Email) {
            abort(422);
        }

        $outreachEmail->update($request->validated());

        return back();
    }

    public function send(
        SendOutreachEmailRequest $request,
        OutreachEmail $outreachEmail,
        OutreachSendService $sender,
    ): RedirectResponse {
        $this->authorize('update', $outreachEmail);

        try {
            $sender->send(
                $request->user(),
                $outreachEmail,
                $request->confirmWarned(),
            );
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->setStatusCode(422);
        } catch (WarmupTransportException) {
            return back()
                ->with('error', 'Unable to send outreach right now. Please try again.')
                ->setStatusCode(422);
        }

        return back()->with('success', 'Outreach sent.');
    }

    public function markSent(Request $request, OutreachEmail $outreachEmail): RedirectResponse
    {
        $this->authorize('update', $outreachEmail);

        if ($outreachEmail->channel === OutreachChannel::Email) {
            abort(422);
        }

        $outreachEmail->update([
            'sent_at' => now(),
            'sent_subject' => $outreachEmail->subject_line,
            'sent_body' => $outreachEmail->email_body,
            'send_source' => OutreachSendSource::Manual,
        ]);

        return back();
    }

    public function markResponse(Request $request, OutreachEmail $outreachEmail): RedirectResponse
    {
        $this->authorize('update', $outreachEmail);

        $outreachEmail->update(['response_received' => true]);

        return back();
    }
}

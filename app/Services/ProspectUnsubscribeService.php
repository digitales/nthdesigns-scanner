<?php

namespace App\Services;

use App\Enums\IgnoredProspectReason;
use App\Enums\SuppressionSource;
use App\Models\IgnoredProspect;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\SuppressedEmail;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

final class ProspectUnsubscribeService
{
    public function __construct(
        private ProspectExclusionService $exclusions,
    ) {}

    public function normalizeEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return strtolower(trim($email));
    }

    public function isSuppressed(User $user, ?string $email): bool
    {
        $normalized = $this->normalizeEmail($email);

        if ($normalized === null) {
            return false;
        }

        return SuppressedEmail::query()
            ->where('user_id', $user->id)
            ->where('email', $normalized)
            ->exists();
    }

    public function outreachSkipReason(User $user, Prospect $prospect): ?string
    {
        if ($this->normalizeEmail($prospect->email) === null) {
            return 'no email';
        }

        if ($this->isSuppressed($user, $prospect->email)) {
            return 'unsubscribed';
        }

        return null;
    }

    public function unsubscribe(User $user, Prospect $prospect, SuppressionSource $source): void
    {
        $normalized = $this->normalizeEmail($prospect->email);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'email' => 'Prospect has no contact email.',
            ]);
        }

        SuppressedEmail::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'email' => $normalized,
            ],
            [
                'source' => $source,
                'prospect_id' => $prospect->id,
            ],
        );

        $this->exclusions->ignore($user, $prospect, IgnoredProspectReason::Unsubscribed);
        $this->removeOutreachSelectionsForEmail($user, $normalized);
    }

    public function signedUnsubscribeUrl(User $user, Prospect $prospect, string $email): string
    {
        return URL::signedRoute('unsubscribe', [
            'prospect' => $prospect->id,
            'email' => $this->normalizeEmail($email),
        ]);
    }

    public function liftSuppression(User $user, ?string $email): void
    {
        $normalized = $this->normalizeEmail($email);

        if ($normalized === null) {
            return;
        }

        SuppressedEmail::query()
            ->where('user_id', $user->id)
            ->where('email', $normalized)
            ->delete();
    }

    public function liftSuppressionForIgnored(User $user, IgnoredProspect $ignored): void
    {
        if ($ignored->reason !== IgnoredProspectReason::Unsubscribed) {
            return;
        }

        $prospect = Prospect::query()
            ->where('place_id', $ignored->place_id)
            ->whereHas('search', fn ($query) => $query->where('user_id', $user->id))
            ->whereNotNull('email')
            ->orderByDesc('id')
            ->first();

        if ($prospect !== null) {
            $this->liftSuppression($user, $prospect->email);
        }
    }

    public function appendUnsubscribeFooter(string $body, User $user, Prospect $prospect, string $email): string
    {
        $url = $this->signedUnsubscribeUrl($user, $prospect, $email);

        return rtrim($body)."\n\n---\nIf you'd prefer not to hear from us, unsubscribe here: {$url}";
    }

    public function bodyContainsUnsubscribeFooter(string $body, User $user, Prospect $prospect, string $email): bool
    {
        $url = $this->signedUnsubscribeUrl($user, $prospect, $email);

        return str_contains($body, $url);
    }

    private function removeOutreachSelectionsForEmail(User $user, string $normalizedEmail): void
    {
        $prospectIds = Prospect::query()
            ->whereHas('search', fn ($query) => $query->where('user_id', $user->id))
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->pluck('id');

        if ($prospectIds->isEmpty()) {
            return;
        }

        OutreachSelection::query()
            ->where('user_id', $user->id)
            ->whereIn('prospect_id', $prospectIds)
            ->delete();
    }
}

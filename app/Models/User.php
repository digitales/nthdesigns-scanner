<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'subscription_tier'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function searches(): HasMany
    {
        return $this->hasMany(Search::class);
    }

    public function outreachSelections(): HasMany
    {
        return $this->hasMany(OutreachSelection::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }

    public function setting(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function userMcpKeys(): HasMany
    {
        return $this->hasMany(UserMcpKey::class);
    }

    public function prospectLists(): HasMany
    {
        return $this->hasMany(ProspectList::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function warmupMailboxes(): HasMany
    {
        return $this->hasMany(WarmupMailbox::class);
    }

    public function warmupTier(): string
    {
        return $this->subscription_tier ?? 'solo';
    }

    /**
     * @return array<string, mixed>
     */
    public function warmupTierLimits(): array
    {
        return config('warmup_tiers.'.$this->warmupTier(), config('warmup_tiers.solo'));
    }
}

<?php

namespace Tests\Unit;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Support\AuditSiteJobDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuditSiteJobDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_stagger_increases_with_prospect_order_in_search(): void
    {
        Config::set('scanner.audit_dispatch_stagger_seconds', 30);

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => ScanType::Combined,
        ]);

        $first = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://one.example',
            'audit_status' => AuditStatus::Pending,
        ]);
        $second = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://two.example',
            'audit_status' => AuditStatus::Pending,
        ]);

        $this->assertSame(0, AuditSiteJobDispatch::staggerDelaySeconds($first));
        $this->assertSame(30, AuditSiteJobDispatch::staggerDelaySeconds($second));
    }

    public function test_dispatch_applies_stagger_to_queue_delay(): void
    {
        Bus::fake();
        Config::set('scanner.audit_dispatch_stagger_seconds', 30);

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => ScanType::Combined,
        ]);
        Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://one.example',
            'audit_status' => AuditStatus::Pending,
        ]);
        $second = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://two.example',
            'audit_status' => AuditStatus::Pending,
        ]);

        AuditSiteJobDispatch::dispatch($second);

        Bus::assertDispatched(AuditSiteJob::class, fn (AuditSiteJob $job) => $job->prospect->id === $second->id);
    }

    public function test_stagger_disabled_when_config_zero(): void
    {
        Config::set('scanner.audit_dispatch_stagger_seconds', 0);

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $this->assertSame(0, AuditSiteJobDispatch::staggerDelaySeconds($prospect));
    }

    public function test_dispatch_caps_stagger_plus_extra_at_sqs_max(): void
    {
        Config::set('scanner.audit_dispatch_stagger_seconds', 30);

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);

        for ($i = 0; $i < 30; $i++) {
            Prospect::factory()->create(['search_id' => $search->id]);
        }

        $late = Prospect::factory()->create(['search_id' => $search->id]);

        $this->assertSame(900, AuditSiteJobDispatch::staggerDelaySeconds($late));

        Bus::fake();

        AuditSiteJobDispatch::dispatch($late, extraDelaySeconds: 20);

        Bus::assertDispatched(AuditSiteJob::class, function (AuditSiteJob $job) use ($late) {
            return $job->prospect->id === $late->id
                && $job->delay !== null
                && $job->delay->lessThanOrEqualTo(now()->addSeconds(900))
                && $job->delay->greaterThanOrEqualTo(now()->addSeconds(899));
        });
    }
}

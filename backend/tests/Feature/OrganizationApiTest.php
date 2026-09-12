<?php

namespace Tests\Feature;

use App\Jobs\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\Review;
use App\Models\SyncRun;
use App\Models\User;
use App\SyncStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_guest_cannot_use_organization_api(): void
    {
        $this->getJson('/api/v1/organizations')->assertUnauthorized();
        $this->postJson('/api/v1/organizations', [])->assertUnauthorized();
        $this->getJson('/api/v1/organizations/1')->assertUnauthorized();
        $this->getJson('/api/v1/organizations/1/reviews')->assertUnauthorized();
        $this->getJson('/api/v1/organizations/1/sync-status')->assertUnauthorized();
    }

    public function test_user_can_connect_yandex_maps_organization(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/organizations', [
            'url' => 'https://yandex.ru/maps/org/test_company/123456/?ll=37.62%2C55.75',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.url', 'https://yandex.ru/maps/org/test_company/123456/?ll=37.62%2C55.75')
            ->assertJsonPath('data.name', null)
            ->assertJsonPath('data.sync.status', SyncStatus::Pending->value)
            ->assertJsonPath('data.sync.progress', 0);

        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'normalized_url' => 'https://yandex.ru/maps/org/test_company/123456',
        ]);
        $this->assertDatabaseHas('sync_runs', [
            'status' => SyncStatus::Pending->value,
            'progress' => 0,
        ]);
        Queue::assertPushed(SyncOrganizationJob::class, function (SyncOrganizationJob $job): bool {
            return $job->organizationId > 0 && $job->syncRunId > 0;
        });
    }

    public function test_equivalent_url_does_not_create_duplicate_organization_or_sync_run(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/organizations', [
            'url' => 'https://www.yandex.ru/maps/org/test_company/123456/?utm_source=test',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/organizations', [
            'url' => 'https://yandex.ru/maps/org/test_company/123456',
        ])->assertOk();

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('sync_runs', 1);
        Queue::assertPushed(SyncOrganizationJob::class, 1);
    }

    public function test_invalid_organization_url_returns_field_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/organizations', [
            'url' => 'https://example.com/maps/org/test',
        ])->assertUnprocessable()->assertJsonValidationErrors(['url']);

        $this->actingAs($user)->postJson('/api/v1/organizations', [
            'url' => 'http://yandex.ru/maps/org/test',
        ])->assertUnprocessable()->assertJsonValidationErrors(['url']);

        $this->actingAs($user)->postJson('/api/v1/organizations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_index_and_show_return_only_users_organizations(): void
    {
        $user = User::factory()->create();
        $ownOrganization = Organization::factory()->for($user)->create();
        $foreignOrganization = Organization::factory()->create();
        SyncRun::factory()->for($ownOrganization)->create();

        $this->actingAs($user)->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownOrganization->id);

        $this->actingAs($user)->getJson("/api/v1/organizations/{$ownOrganization->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownOrganization->id);

        $this->actingAs($user)->getJson("/api/v1/organizations/{$foreignOrganization->id}")
            ->assertNotFound();
    }

    public function test_reviews_are_paginated_by_fifty_and_isolated_by_owner(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create();
        $foreignOrganization = Organization::factory()->create();
        Review::factory()->count(55)->for($organization)->create();

        $this->actingAs($user)->getJson("/api/v1/organizations/{$organization->id}/reviews")
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 55);

        $this->actingAs($user)->getJson("/api/v1/organizations/{$organization->id}/reviews?page=2")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);

        $this->actingAs($user)->getJson("/api/v1/organizations/{$foreignOrganization->id}/reviews")
            ->assertNotFound();
    }

    public function test_user_can_read_latest_sync_status_only_for_own_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create();
        $foreignOrganization = Organization::factory()->create();
        SyncRun::factory()->for($organization)->create([
            'status' => SyncStatus::Running,
            'progress' => 35,
            'processed_reviews' => 120,
        ]);

        $this->actingAs($user)->getJson("/api/v1/organizations/{$organization->id}/sync-status")
            ->assertOk()
            ->assertJsonPath('data.status', SyncStatus::Running->value)
            ->assertJsonPath('data.progress', 35)
            ->assertJsonPath('data.processed_reviews', 120);

        $this->actingAs($user)->getJson("/api/v1/organizations/{$foreignOrganization->id}/sync-status")
            ->assertNotFound();
    }
}

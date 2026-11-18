<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 1 DoD (a): 招待コードなしで非公開コミュニティに参加できないこと。
 * Phase 1 DoD (b): requestポリシーのコミュニティで参加がpendingのまま機体操作ができないこと。
 * （設計書9章 Phase 1 DoD）
 */
class MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_招待コードなしでは非公開コミュニティに参加できない(): void
    {
        $owner = User::factory()->create();
        $community = Community::create([
            'name' => 'Private Wing', 'slug' => 'private-wing',
            'visibility' => 'private', 'join_policy' => 'invite_only',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/communities/{$community->id}/join");

        $response->assertStatus(403);
        $this->assertDatabaseMissing('community_memberships', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_招待コードで参加すると即座にactiveになる(): void
    {
        $owner = User::factory()->create();
        $community = Community::create([
            'name' => 'Wing A', 'slug' => 'wing-a',
            'visibility' => 'private', 'join_policy' => 'invite_only',
        ]);
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'code' => 'ABC12345',
            'created_by_user_id' => $owner->id,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/communities/join-by-code', ['code' => 'ABC12345']);

        $response->assertOk();
        $this->assertDatabaseHas('community_memberships', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $this->assertEquals(1, $invite->fresh()->uses_count);
    }

    public function test_requestポリシーではpendingのまま機体一覧を取得できない(): void
    {
        $community = Community::create([
            'name' => 'Open Request Wing', 'slug' => 'open-request-wing',
            'visibility' => 'public', 'join_policy' => 'request',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $joinResponse = $this->postJson("/api/v1/communities/{$community->id}/join");
        $joinResponse->assertStatus(202);
        $this->assertDatabaseHas('community_memberships', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        // pendingのままでは機体一覧(membershipミドルウェア配下)にアクセスできないこと。
        $aircraftResponse = $this->getJson("/api/v1/communities/{$community->id}/aircraft");
        $aircraftResponse->assertStatus(403);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 1 DoD (c): 2つのセッションから同じ機体を同時に予約して片方が409になること。
 *
 * 注意（確度: 中）: PHPUnitは単一プロセス・単一DB接続で動くため、本当の意味での
 * 「同時リクエスト」はここでは再現できない。ここでは reserve_aircraft() の
 * ガード条件（status!='available' なら409）が正しく機能することを、
 * 「1件目の予約が成功した直後に2件目を投げると409になる」という形で検証している。
 * 真の競合（2プロセスからの同時FOR UPDATE待ち）は、負荷試験ツール(k6等)による
 * 結合テストをステージング環境で別途行うことを推奨する。
 */
class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private function makeMemberAircraft(): array
    {
        $community = Community::create([
            'name' => 'Test Wing', 'slug' => 'test-wing',
            'visibility' => 'private', 'join_policy' => 'invite_only',
        ]);

        $aircraft = Aircraft::create([
            'community_id' => $community->id,
            'registration' => 'JA123A',
            'icao_type' => 'B738',
            'sim_platform' => 'ANY',
            'home_base_icao' => 'RJTT',
            'current_location_icao' => 'RJTT',
            'status' => 'available',
        ]);

        return [$community, $aircraft];
    }

    private function memberUser(Community $community): User
    {
        $user = User::factory()->create();
        CommunityMembership::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => 'pilot',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }

    public function test_同じ機体を2回連続で予約すると2回目は409になる(): void
    {
        [$community, $aircraft] = $this->makeMemberAircraft();
        $userA = $this->memberUser($community);
        $userB = $this->memberUser($community);

        Sanctum::actingAs($userA);
        $first = $this->postJson("/api/v1/aircraft/{$aircraft->id}/reserve");
        $first->assertStatus(201);

        Sanctum::actingAs($userB);
        $second = $this->postJson("/api/v1/aircraft/{$aircraft->id}/reserve");
        $second->assertStatus(409);
        $second->assertJson(['error' => 'in_use']);

        $this->assertSame('reserved', $aircraft->fresh()->status);
        $this->assertSame(1, $aircraft->fresh()->version);
    }

    public function test_メンバーでないユーザーは予約できない(): void
    {
        [$community, $aircraft] = $this->makeMemberAircraft();
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);
        $response = $this->postJson("/api/v1/aircraft/{$aircraft->id}/reserve");

        $response->assertStatus(403);
        $response->assertJson(['error' => 'not_a_member']);
    }

    public function test_予約解放後は再度予約できる(): void
    {
        [$community, $aircraft] = $this->makeMemberAircraft();
        $user = $this->memberUser($community);
        Sanctum::actingAs($user);

        $reserve = $this->postJson("/api/v1/aircraft/{$aircraft->id}/reserve");
        $reservationId = $reserve->json('reservation_id');

        $release = $this->postJson("/api/v1/reservations/{$reservationId}/release");
        $release->assertOk();
        $this->assertSame('available', $aircraft->fresh()->status);

        $reserveAgain = $this->postJson("/api/v1/aircraft/{$aircraft->id}/reserve");
        $reserveAgain->assertStatus(201);
    }
}

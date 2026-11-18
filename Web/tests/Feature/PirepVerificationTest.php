<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AircraftReservation;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\Pirep;
use App\Models\User;
use App\Models\VatsimSnapshot;
use App\Services\VatsimVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 DoD: テスト用のダミーVATSIMスナップショットを投入し、
 * 対応するPIREPが自動検証されることを確認する（設計書9章・4.2節）。
 */
class PirepVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_一致するVATSIMスナップショットがあるPIREPはauto_verifiedになる(): void
    {
        $user = User::factory()->create(['vatsim_cid' => 1234567]);
        $community = Community::create(['name' => 'W', 'slug' => 'w', 'visibility' => 'private', 'join_policy' => 'invite_only']);
        CommunityMembership::create(['community_id' => $community->id, 'user_id' => $user->id, 'role' => 'pilot', 'status' => 'active', 'joined_at' => now()]);
        $aircraft = Aircraft::create([
            'community_id' => $community->id, 'registration' => 'JA1XY', 'icao_type' => 'A320',
            'home_base_icao' => 'RJTT', 'current_location_icao' => 'RJTT', 'status' => 'reserved',
        ]);
        $reservation = AircraftReservation::create([
            'aircraft_id' => $aircraft->id, 'reserved_by_user_id' => $user->id,
            'reserved_at' => now(), 'expires_at' => now()->addHours(12),
        ]);

        $departureTime = now()->subHour();

        $pirep = Pirep::create([
            'aircraft_id' => $aircraft->id, 'user_id' => $user->id, 'reservation_id' => $reservation->id,
            'departure_icao' => 'RJTT', 'arrival_icao' => 'RJOO',
            'departure_time' => $departureTime, 'arrival_time' => now(),
            'flight_time_min' => 60, 'network' => 'vatsim', 'vatsim_callsign' => 'ANA123',
            'verification_status' => 'pending',
        ]);

        VatsimSnapshot::create([
            'callsign' => 'ANA123', 'cid' => 1234567,
            'departure' => 'RJTT', 'arrival' => 'RJOO',
            'logon_time' => $departureTime->copy()->subMinutes(5),
            'last_seen_at' => now(),
        ]);

        app(VatsimVerificationService::class)->verifyPirep($pirep->id);

        $this->assertSame('auto_verified', $pirep->fresh()->verification_status);
    }

    public function test_一致するスナップショットがなければpendingのまま(): void
    {
        $user = User::factory()->create(['vatsim_cid' => 7654321]);
        $community = Community::create(['name' => 'W2', 'slug' => 'w2', 'visibility' => 'private', 'join_policy' => 'invite_only']);
        CommunityMembership::create(['community_id' => $community->id, 'user_id' => $user->id, 'role' => 'pilot', 'status' => 'active', 'joined_at' => now()]);
        $aircraft = Aircraft::create([
            'community_id' => $community->id, 'registration' => 'JA2XY', 'icao_type' => 'A320',
            'home_base_icao' => 'RJTT', 'current_location_icao' => 'RJTT', 'status' => 'reserved',
        ]);
        $reservation = AircraftReservation::create([
            'aircraft_id' => $aircraft->id, 'reserved_by_user_id' => $user->id,
            'reserved_at' => now(), 'expires_at' => now()->addHours(12),
        ]);

        $pirep = Pirep::create([
            'aircraft_id' => $aircraft->id, 'user_id' => $user->id, 'reservation_id' => $reservation->id,
            'departure_icao' => 'RJTT', 'arrival_icao' => 'RJOO',
            'departure_time' => now()->subHour(), 'arrival_time' => now(),
            'flight_time_min' => 60, 'network' => 'vatsim', 'vatsim_callsign' => 'ANA999',
            'verification_status' => 'pending',
        ]);

        app(VatsimVerificationService::class)->verifyPirep($pirep->id);

        $this->assertSame('pending', $pirep->fresh()->verification_status);
    }
}

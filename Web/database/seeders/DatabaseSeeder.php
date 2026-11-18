<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $owner = User::factory()->create([
            'discord_id' => '100000000000000001',
            'discord_username' => 'owner_sample',
            'email' => 'owner@example.com',
        ]);

        $community = Community::create([
            'name' => 'サンプル・バーチャル航空',
            'slug' => 'sample-va',
            'description' => '動作確認用のサンプルコミュニティです',
            'visibility' => 'public',
            'join_policy' => 'open',
            'is_recruiting' => true,
            'recruiting_message' => '初心者歓迎です！',
        ]);

        CommunityMembership::create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Aircraft::create([
            'community_id' => $community->id,
            'registration' => 'JA207A',
            'icao_type' => 'B738',
            'livery_name' => 'ANA Standard',
            'sim_platform' => 'ANY',
            'home_base_icao' => 'RJTT',
            'current_location_icao' => 'RJTT',
            'status' => 'available',
        ]);
    }
}

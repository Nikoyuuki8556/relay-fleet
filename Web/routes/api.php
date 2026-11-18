<?php

use App\Http\Controllers\Api\AircraftController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\PirepController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Auth\NativeAuthController;
use Illuminate\Support\Facades\Route;

// 5章「Web API仕様（Laravel Sanctum認証）」
// 全エンドポイントは Authorization: Bearer <token> 必須。ベースパスは /api/v1。
Route::prefix('v1')->group(function () {

    // --- 7.4節: Windowsアプリのネイティブトークン交換（未認証で叩く） ---
    Route::post('/auth/discord/token', [NativeAuthController::class, 'discordToken']);
    Route::post('/auth/vatsim/token', [NativeAuthController::class, 'vatsimToken']);

    Route::middleware('auth:sanctum')->group(function () {
        // --- 5.1 コミュニティ・メンバーシップ関連 ---
        Route::post('/communities', [CommunityController::class, 'store']);
        Route::get('/communities', [CommunityController::class, 'index']);
        Route::get('/communities/{community}', [CommunityController::class, 'show']);
        Route::post('/communities/{community}/join', [CommunityController::class, 'join']);
        Route::post('/communities/join-by-code', [MembershipController::class, 'joinByCode']);
        Route::post('/communities/{community}/invites', [MembershipController::class, 'createInvite']);
        Route::get('/communities/{community}/memberships', [MembershipController::class, 'index']);
        Route::post('/communities/{community}/memberships/{user_id}/approve', [MembershipController::class, 'approve']);
        Route::patch('/communities/{community}/memberships/{user_id}', [MembershipController::class, 'update']);
        Route::get('/me/communities', [MembershipController::class, 'myCommunities']);
        Route::get('/me', [MeController::class, 'show']);

        // --- 5.2 機体・PIREP関連（すべて has_active_membership チェックあり） ---
        // {community}を持つエンドポイントは 'membership' ミドルウェアで一律チェックする。
        Route::middleware('membership')->group(function () {
            Route::get('/communities/{community}/aircraft', [AircraftController::class, 'index']);
        });

        // aircraft_id / reservation_id 起点のエンドポイントは、コントローラ内で
        // community_idを解決したうえで hasActiveMembership() を明示的にチェックする。
        Route::get('/aircraft/{aircraft}', [AircraftController::class, 'show']);
        Route::post('/aircraft/{aircraft}/reserve', [ReservationController::class, 'store']);
        Route::post('/reservations/{reservation}/release', [ReservationController::class, 'release']);
        Route::post('/pireps', [PirepController::class, 'store']);
        Route::get('/pireps', [PirepController::class, 'index']);
    });
});

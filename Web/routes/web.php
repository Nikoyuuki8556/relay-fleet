<?php

use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\DiscordInteractionController;
use App\Http\Controllers\Web\AircraftWebController;
use App\Http\Controllers\Web\AirportController;
use App\Http\Controllers\Web\CommunityManagementController;
use App\Http\Controllers\Web\CommunityMembersController;
use App\Http\Controllers\Web\CommunityWebController;
use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('welcome');
})->name('welcome');

// --- 認証（ブラウザ用、OAuth2 Authorization Codeフロー） ---
Route::get('/auth/discord/redirect', [WebAuthController::class, 'discordRedirect'])->name('auth.discord.redirect');
Route::get('/auth/discord/callback', [WebAuthController::class, 'discordCallback'])->name('auth.discord.callback');
Route::get('/auth/vatsim/redirect', [WebAuthController::class, 'vatsimRedirect'])->name('auth.vatsim.redirect');
Route::get('/auth/vatsim/callback', [WebAuthController::class, 'vatsimCallback'])->name('auth.vatsim.callback');
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// --- 6.2節: Discord Interactions Endpoint（署名検証あり、Discord Developer Portalに登録するURL） ---
Route::post('/discord/interactions', [DiscordInteractionController::class, 'handle'])->name('discord.interactions');

// --- ブラウザ用画面（session認証） ---
// Web/*Controller は5章のREST APIを経由せず、App\Services\* を直接呼ぶ
// （設計書2章「Windowsアプリはこちらのみ利用」の通り、REST APIはネイティブアプリ専用という位置づけのため）。
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/communities/directory', [CommunityWebController::class, 'directory'])->name('communities.directory');
    Route::get('/communities/create', [CommunityWebController::class, 'create'])->name('communities.create');
    Route::post('/communities', [CommunityWebController::class, 'store'])->name('communities.store');
    Route::post('/communities/join-by-code', [CommunityWebController::class, 'joinByCode'])->name('communities.join-by-code');
    Route::post('/communities/{community}/join', [CommunityWebController::class, 'join'])->name('communities.join');
    Route::get('/communities/{community}', [CommunityWebController::class, 'show'])->name('communities.show');
    Route::get('/communities/{community}/ranking', [CommunityWebController::class, 'ranking'])->name('communities.ranking');
    Route::get('/communities/{community}/map', [CommunityWebController::class, 'map'])->name('communities.map');
    Route::post('/airports', [AirportController::class, 'store'])->name('airports.store');
    Route::post('/communities/{community}/invites', [CommunityWebController::class, 'createInvite'])->name('communities.invites.store');
    Route::post('/communities/{community}/memberships/{user}/approve', [CommunityWebController::class, 'approveMembership'])->name('communities.memberships.approve');

    // --- コミュニティ詳細管理（owner/admin） ---
    Route::get('/communities/{community}/manage', [CommunityManagementController::class, 'edit'])->name('communities.manage');
    Route::post('/communities/{community}/settings', [CommunityManagementController::class, 'update'])->name('communities.settings.update');
    Route::post('/communities/{community}/links', [CommunityManagementController::class, 'storeLink'])->name('communities.links.store');
    Route::post('/communities/{community}/links/{link}/delete', [CommunityManagementController::class, 'destroyLink'])->name('communities.links.destroy');
    Route::post('/communities/{community}/airlines', [CommunityManagementController::class, 'storeAirline'])->name('communities.airlines.store');
    Route::post('/communities/{community}/airlines/{airline}/delete', [CommunityManagementController::class, 'destroyAirline'])->name('communities.airlines.destroy');
    Route::post('/communities/{community}/aircraft', [CommunityManagementController::class, 'storeAircraft'])->name('communities.aircraft.store');
    Route::post('/communities/{community}/aircraft/{aircraft}/update', [CommunityManagementController::class, 'updateAircraft'])->name('communities.aircraft.update');
    Route::post('/communities/{community}/aircraft/{aircraft}/retire', [CommunityManagementController::class, 'retireAircraft'])->name('communities.aircraft.retire');
    Route::post('/communities/{community}/aircraft/{aircraft}/maintenance-interval', [CommunityManagementController::class, 'updateMaintenanceInterval'])->name('communities.aircraft.maintenance-interval');
    Route::get('/communities/{community}/audit-log', [CommunityManagementController::class, 'auditLog'])->name('communities.audit-log');

    // --- メンバー管理・ロール変更（owner/admin） ---
    Route::get('/communities/{community}/members', [CommunityMembersController::class, 'index'])->name('communities.members');
    Route::post('/communities/{community}/members/{user}', [CommunityMembersController::class, 'update'])->name('communities.members.update');

    // --- 機体詳細・運用予定 ---
    Route::get('/communities/{community}/aircraft/{aircraft}', [CommunityWebController::class, 'aircraftShow'])->name('communities.aircraft.show');
    Route::post('/communities/{community}/aircraft/{aircraft}/schedules', [CommunityWebController::class, 'storeSchedule'])->name('aircraft.schedules.store');
    Route::post('/communities/{community}/aircraft/{aircraft}/schedules/{schedule}/delete', [CommunityWebController::class, 'destroySchedule'])->name('aircraft.schedules.destroy');

    Route::post('/aircraft/{aircraft}/reserve', [AircraftWebController::class, 'reserve'])->name('aircraft.reserve');
    Route::post('/reservations/{reservation}/release', [AircraftWebController::class, 'release'])->name('reservations.release');
    Route::post('/aircraft/{aircraft}/queue', [AircraftWebController::class, 'joinQueue'])->name('aircraft.queue.join');
    Route::post('/reservation-queue/{queueEntry}/leave', [AircraftWebController::class, 'leaveQueue'])->name('aircraft.queue.leave');
    Route::post('/pireps', [AircraftWebController::class, 'submitPirep'])->name('pireps.store');
});

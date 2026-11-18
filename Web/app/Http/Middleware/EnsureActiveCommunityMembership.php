<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\MembershipService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 3.2節「認可の原則」: aircraft/aircraft_reservations/pirepsに関わる全APIは
 * まず has_active_membership() を通すこと。
 *
 * {community} ルートパラメータを持つエンドポイント専用。
 * aircraft_id / reservation_id 起点のエンドポイントは、community_idをモデルから解決する必要があるため
 * このミドルウェアではなく各サービス内で hasActiveMembership() を明示的に呼ぶ（ReservationService/PirepService参照）。
 */
class EnsureActiveCommunityMembership
{
    public function __construct(private readonly MembershipService $memberships)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $communityId = (int) $request->route('community');
        $user = $request->user();

        if (! $user || ! $this->memberships->hasActiveMembership($user->id, $communityId)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }

        return $next($request);
    }
}

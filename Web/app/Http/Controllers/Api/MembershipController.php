<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipService $memberships)
    {
    }

    // POST /communities/join-by-code {"code":"ABC123"}
    public function joinByCode(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $membership = $this->memberships->joinViaInviteCode($request->user(), $data['code']);

        return response()->json($membership->load('community'), 200);
    }

    // POST /communities/{id}/invites（owner/adminのみ）
    public function createInvite(Request $request, Community $community)
    {
        $data = $request->validate([
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $invite = $this->memberships->createInvite(
            $request->user(),
            $community,
            $data['max_uses'] ?? null,
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );

        return response()->json($invite, 201);
    }

    // GET /communities/{id}/memberships?status=pending（owner/adminのみ）
    public function index(Request $request, Community $community)
    {
        if (! $this->memberships->hasRole($request->user()->id, $community->id, ['owner', 'admin'])) {
            abort(403, '権限がありません');
        }

        $query = $community->memberships()->with('user');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('id')->paginate(50);
    }

    // POST /communities/{id}/memberships/{user_id}/approve
    public function approve(Request $request, Community $community, int $userId)
    {
        $this->memberships->approveMembership($request->user(), $community, $userId);

        return response()->json(['status' => 'active']);
    }

    // PATCH /communities/{id}/memberships/{user_id}（ロール変更・BAN、owner/adminのみ）
    public function update(Request $request, Community $community, int $userId)
    {
        $data = $request->validate([
            'role' => ['nullable', 'in:owner,admin,dispatcher,pilot'],
            'status' => ['nullable', 'in:active,pending,banned'],
        ]);

        $membership = $this->memberships->changeRoleOrBan(
            $request->user(), $community, $userId, $data['role'] ?? null, $data['status'] ?? null,
        );

        return response()->json($membership);
    }

    // GET /me/communities
    public function myCommunities(Request $request)
    {
        return $request->user()->memberships()->with('community')->get();
    }
}

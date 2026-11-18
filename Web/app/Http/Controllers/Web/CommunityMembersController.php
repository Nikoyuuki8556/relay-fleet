<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class CommunityMembersController extends Controller
{
    public function __construct(private readonly MembershipService $memberships)
    {
    }

    public function index(Request $request, Community $community)
    {
        if (! $this->memberships->hasRole($request->user()->id, $community->id, ['owner', 'admin'])) {
            throw new ApiException(403, 'forbidden', '権限がありません');
        }

        // MySQL専用のFIELD()を避け、PHP側でソートしてDBポータビリティを保つ。
        $statusOrder = ['pending' => 0, 'active' => 1, 'banned' => 2];
        $roleOrder = ['owner' => 0, 'admin' => 1, 'dispatcher' => 2, 'pilot' => 3];
        $members = $community->memberships()->with('user')->get()
            ->sortBy(fn ($m) => ($statusOrder[$m->status] ?? 9) * 10 + ($roleOrder[$m->role] ?? 9))
            ->values();

        return view('communities.members', ['community' => $community, 'members' => $members]);
    }

    public function update(Request $request, Community $community, int $userId)
    {
        $data = $request->validate([
            'role' => ['nullable', 'in:owner,admin,dispatcher,pilot'],
            'status' => ['nullable', 'in:active,pending,banned'],
        ]);

        if (empty($data)) {
            return redirect()->route('communities.members', $community);
        }

        try {
            $this->memberships->changeRoleOrBan(
                $request->user(), $community, $userId, $data['role'] ?? null, $data['status'] ?? null,
            );

            return redirect()->route('communities.members', $community)->with('status', 'メンバー情報を更新しました');
        } catch (ApiException $e) {
            return redirect()->route('communities.members', $community)->with('error', $e->getMessage());
        }
    }
}

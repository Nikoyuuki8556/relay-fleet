<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\Community;
use App\Services\MembershipService;
use Illuminate\Http\Request;

// GET /communities/{id}/aircraft は routes/api.php で 'membership' ミドルウェアを通す。
// GET /aircraft/{id} は {community} パラメータを持たないため、ここでインラインチェックする。
class AircraftController extends Controller
{
    public function __construct(private readonly MembershipService $memberships)
    {
    }

    public function index(Community $community)
    {
        return $community->aircraft()->orderBy('registration')->get();
    }

    public function show(Request $request, Aircraft $aircraft)
    {
        if (! $this->memberships->hasActiveMembership($request->user()->id, $aircraft->community_id)) {
            throw new ApiException(403, 'not_a_member', 'このコミュニティのメンバーではありません');
        }

        return $aircraft;
    }
}

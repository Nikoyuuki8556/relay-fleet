<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function __construct(private readonly MembershipService $memberships)
    {
    }

    // GET /communities?visibility=public&recruiting=true
    public function index(Request $request)
    {
        $query = Community::query();

        if ($request->query('visibility') === 'public') {
            $query->where('visibility', 'public');
        }
        if ($request->boolean('recruiting')) {
            $query->where('is_recruiting', true);
        }

        return $query->orderByDesc('id')->paginate(20);
    }

    // POST /communities（作成者がownerになる）
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:communities,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'visibility' => ['nullable', 'in:public,private'],
            'join_policy' => ['nullable', 'in:open,request,invite_only'],
            'is_recruiting' => ['nullable', 'boolean'],
            'recruiting_message' => ['nullable', 'string', 'max:255'],
        ]);

        $community = $this->memberships->createCommunity($request->user(), $data);

        return response()->json($community, 201);
    }

    // GET /communities/{id}
    public function show(Community $community)
    {
        return $community;
    }

    // POST /communities/{id}/join
    public function join(Request $request, Community $community)
    {
        $result = $this->memberships->joinPublicCommunity($request->user(), $community);

        return response()->json($result, $result['status'] === 'pending' ? 202 : 200);
    }
}

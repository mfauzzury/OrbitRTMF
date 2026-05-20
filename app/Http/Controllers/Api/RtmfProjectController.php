<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRtmfProjectMemberRequest;
use App\Http\Requests\StoreRtmfProjectRequest;
use App\Http\Requests\UpdateRtmfProjectMemberRequest;
use App\Http\Requests\UpdateRtmfProjectRequest;
use App\Http\Traits\ApiResponse;
use App\Models\RtmfProject;
use App\Services\RtmfMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RtmfProjectController extends Controller
{
    use ApiResponse;

    public function __construct(protected RtmfMemberService $memberService) {}

    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        $query = RtmfProject::orderBy('sort_order')->orderBy('name');

        if (! $isAdmin) {
            $query->whereHas('members', fn ($q) => $q->where('users.id', $user->id));
        }

        $rows = $query->get();

        if ($isAdmin) {
            $rows = $rows->map(fn ($p) => array_merge($p->toArray(), ['my_role' => 'admin']));
        } else {
            $myRoles = DB::table('rtmf_project_users')
                ->where('user_id', $user->id)
                ->whereIn('project_id', $rows->pluck('id'))
                ->pluck('role', 'project_id');

            $rows = $rows->map(fn ($p) => array_merge($p->toArray(), ['my_role' => $myRoles->get($p->id, 'viewer')]));
        }

        return $this->sendOk($rows, ['total' => count($rows)]);
    }

    public function show(int $id): JsonResponse
    {
        $row = RtmfProject::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Project not found');
        }

        return $this->sendOk($row);
    }

    public function store(StoreRtmfProjectRequest $request): JsonResponse
    {
        $row = RtmfProject::create($request->validated());

        return $this->sendCreated($row);
    }

    public function update(UpdateRtmfProjectRequest $request, int $id): JsonResponse
    {
        $row = RtmfProject::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Project not found');
        }
        $row->update($request->validated());

        return $this->sendOk($row);
    }

    public function destroy(int $id): JsonResponse
    {
        RtmfProject::where('id', $id)->delete();

        return $this->sendOk(['success' => true]);
    }

    // ── Member management ──

    public function members(int $id): JsonResponse
    {
        $project = RtmfProject::find($id);
        if (! $project) {
            return $this->sendError(404, 'NOT_FOUND', 'Project not found');
        }

        $members = DB::table('rtmf_project_users')
            ->join('users', 'users.id', '=', 'rtmf_project_users.user_id')
            ->where('rtmf_project_users.project_id', $id)
            ->orderBy('users.name')
            ->select('users.id', 'users.name', 'users.email', 'users.role', 'users.photo_url', 'rtmf_project_users.role as project_role')
            ->get();

        // Fetch avatars from external identity provider keyed by email
        $externalAvatars = collect();
        $emails = $members->pluck('email')->filter()->values()->toArray();
        if ($emails) {
            try {
                $externalAvatars = DB::connection('mysql_external')
                    ->table('User')
                    ->whereIn('email', $emails)
                    ->pluck('avatarUrl', 'email');
            } catch (\Throwable) {
                // External DB unavailable — fall through to local photo_url
            }
        }

        $result = $members->map(fn ($m) => array_merge((array) $m, [
            'photo_url' => $externalAvatars->get($m->email)
                ?? ($m->photo_url ? url($m->photo_url) : null),
        ]));

        return $this->sendOk($result);
    }

    public function addMember(StoreRtmfProjectMemberRequest $request, int $id): JsonResponse
    {
        $project = RtmfProject::find($id);
        if (! $project) {
            return $this->sendError(404, 'NOT_FOUND', 'Project not found');
        }

        $localUser = \App\Models\User::find($request->input('user_id'));

        if (! $localUser) {
            return $this->sendError(404, 'USER_NOT_FOUND', 'User not found.');
        }

        DB::table('rtmf_project_users')->insertOrIgnore([
            'project_id' => $id,
            'user_id'    => $localUser->id,
            'role'       => $request->input('project_role'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->sendOk(['success' => true]);
    }

    public function updateMember(UpdateRtmfProjectMemberRequest $request, int $id, int $userId): JsonResponse
    {
        DB::table('rtmf_project_users')
            ->where('project_id', $id)
            ->where('user_id', $userId)
            ->update(['role' => $request->input('project_role'), 'updated_at' => now()]);

        return $this->sendOk(['success' => true]);
    }

    public function removeMember(int $id, int $userId): JsonResponse
    {
        DB::table('rtmf_project_users')
            ->where('project_id', $id)
            ->where('user_id', $userId)
            ->delete();

        return $this->sendOk(['success' => true]);
    }

    public function candidates(int $id, Request $request): JsonResponse
    {
        $project = RtmfProject::find($id);
        if (! $project) {
            return $this->sendError(404, 'NOT_FOUND', 'Project not found');
        }

        $q         = $request->input('q');
        $memberIds = $project->members()->pluck('users.id');

        $query = \App\Models\User::whereNotIn('id', $memberIds)->orderBy('name');
        if ($q) {
            $query->where(function ($b) use ($q) {
                $b->where('name', 'ilike', "%{$q}%")->orWhere('email', 'ilike', "%{$q}%");
            });
        }

        $rows = $query->select('id', 'name', 'email', 'role')->limit(100)->get();

        return $this->sendOk($rows);
    }
}

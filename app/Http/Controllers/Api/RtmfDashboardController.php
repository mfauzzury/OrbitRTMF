<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\RtmfActor;
use App\Models\RtmfFrontend;
use App\Models\RtmfFrontendFeedback;
use App\Models\RtmfFrontendItem;
use App\Models\RtmfFrontendScenarioGroup;
use App\Models\RtmfModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RtmfDashboardController extends Controller
{
    use ApiResponse;

    public function summary(Request $request): JsonResponse
    {
        $projectId = $request->integer('project_id') ?: null;

        // Pre-compute scoped IDs once so every subsequent query uses a fast IN clause
        // instead of correlated whereHas subqueries.
        $moduleIds   = $projectId
            ? RtmfModule::where('project_id', $projectId)->pluck('id')
            : null;

        $frontendIds = $moduleIds !== null
            ? RtmfFrontend::whereIn('module_id', $moduleIds)->pluck('id')
            : RtmfFrontend::pluck('id');

        // ── Totals + item status in one pass each ────────────────────────────
        $totalFrontends = $frontendIds->count();
        $totalModules   = $moduleIds !== null ? $moduleIds->count() : RtmfModule::count();

        // Frontends: done count + item status + scenario count — 3 queries combined into aggregates
        [$totalDone, $totalScenarios] = [
            RtmfFrontend::whereIn('id', $frontendIds)->where('is_done', true)->count(),
            RtmfFrontendScenarioGroup::whereIn('rtmf_frontend_id', $frontendIds)->count(),
        ];

        $statusRows = RtmfFrontendItem::select('status', DB::raw('count(*) as total'))
            ->whereIn('rtmf_frontend_id', $frontendIds)
            ->groupBy('status')
            ->get();

        $statusCounts = $statusRows->whereNotNull('status')->pluck('total', 'status')->toArray();
        $nullCount    = (int) ($statusRows->whereStrict('status', null)->first()?->total ?? 0);
        $totalItems   = array_sum($statusCounts) + $nullCount;

        $itemsByStatus = [
            'implemented' => (int) ($statusCounts['implemented'] ?? 0),
            'partial'     => (int) ($statusCounts['partial'] ?? 0),
            'missing'     => (int) ($statusCounts['missing'] ?? 0),
            'unset'       => $nullCount,
        ];

        // ── Per-module breakdown ─────────────────────────────────────────────
        $frontendStats = RtmfFrontend::select(
            'module_id',
            DB::raw('count(*) as frontends_count'),
            DB::raw('sum(case when is_done then 1 else 0 end) as done_count')
        )
            ->whereIn('id', $frontendIds)
            ->groupBy('module_id')
            ->get()
            ->keyBy('module_id');

        $itemStats = RtmfFrontendItem::select(
            'rtmf_frontends.module_id',
            DB::raw('count(*) as items_count'),
            DB::raw("sum(case when rtmf_frontend_items.status = 'implemented' then 1 else 0 end) as implemented_count")
        )
            ->join('rtmf_frontends', 'rtmf_frontend_items.rtmf_frontend_id', '=', 'rtmf_frontends.id')
            ->whereIn('rtmf_frontend_items.rtmf_frontend_id', $frontendIds)
            ->groupBy('rtmf_frontends.module_id')
            ->get()
            ->keyBy('module_id');

        $modulesQuery = RtmfModule::select('id', 'code', 'name')->orderBy('sort_order')->orderBy('id');
        if ($moduleIds !== null) {
            $modulesQuery->whereIn('id', $moduleIds);
        }

        $modules  = $modulesQuery->get();
        $byModule = $modules->map(function ($module) use ($frontendStats, $itemStats) {
            $fs = $frontendStats->get($module->id);
            $is = $itemStats->get($module->id);

            return [
                'id'               => $module->id,
                'code'             => $module->code,
                'name'             => $module->name,
                'frontendsCount'   => (int) ($fs?->frontends_count ?? 0),
                'doneCount'        => (int) ($fs?->done_count ?? 0),
                'itemsCount'       => (int) ($is?->items_count ?? 0),
                'implementedCount' => (int) ($is?->implemented_count ?? 0),
            ];
        });

        // ── Per-actor breakdown (also supplies totalActors count) ────────────
        $actorQuery = RtmfActor::select('id', 'name')->orderBy('sort_order')->orderBy('id');
        if ($projectId) {
            $actorQuery->where('project_id', $projectId);
        }
        $actors      = $actorQuery->get();
        $totalActors = $actors->count();

        $actorStats = DB::table('rtmf_frontend_actor')
            ->select('rtmf_actor_id', DB::raw('count(*) as frontends_count'))
            ->whereIn('rtmf_actor_id', $actors->pluck('id'))
            ->whereIn('rtmf_frontend_id', $frontendIds)
            ->groupBy('rtmf_actor_id')
            ->pluck('frontends_count', 'rtmf_actor_id');

        $byActor = $actors->map(fn ($actor) => [
            'id'             => $actor->id,
            'name'           => $actor->name,
            'frontendsCount' => (int) ($actorStats->get($actor->id) ?? 0),
        ]);

        // ── Feedback breakdown ───────────────────────────────────────────────
        $roles         = ['business_analyst', 'qa', 'technical', 'developer'];
        $feedbackCounts = RtmfFrontendFeedback::select('role', 'status', DB::raw('count(*) as total'))
            ->whereIn('rtmf_frontend_id', $frontendIds)
            ->groupBy('role', 'status')
            ->get();

        $byReview = [];
        foreach ($roles as $role) {
            $rows     = $feedbackCounts->where('role', $role);
            $approved = (int) ($rows->where('status', 'approved')->first()?->total ?? 0);
            $reviewed = (int) ($rows->where('status', 'reviewed')->first()?->total ?? 0);
            $byReview[$role] = [
                'approved' => $approved,
                'reviewed' => $reviewed,
                'open'     => $totalFrontends - $approved - $reviewed,
            ];
        }

        // ── Per-module feedback breakdown per role (drill-down) ─────────────
        $moduleRoleFeedback = RtmfFrontendFeedback::select(
            'rtmf_frontends.module_id',
            'rtmf_frontend_feedbacks.role',
            'rtmf_frontend_feedbacks.status',
            DB::raw('count(*) as total')
        )
            ->join('rtmf_frontends', 'rtmf_frontend_feedbacks.rtmf_frontend_id', '=', 'rtmf_frontends.id')
            ->whereIn('rtmf_frontend_feedbacks.rtmf_frontend_id', $frontendIds)
            ->groupBy('rtmf_frontends.module_id', 'rtmf_frontend_feedbacks.role', 'rtmf_frontend_feedbacks.status')
            ->get();

        $byRoleModule = [];
        foreach ($roles as $role) {
            $byRoleModule[$role] = $modules->map(function ($module) use ($moduleRoleFeedback, $role, $frontendStats) {
                $rows     = $moduleRoleFeedback->where('module_id', $module->id)->where('role', $role);
                $total    = (int) ($frontendStats->get($module->id)?->frontends_count ?? 0);
                $approved = (int) ($rows->where('status', 'approved')->first()?->total ?? 0);
                $reviewed = (int) ($rows->where('status', 'reviewed')->first()?->total ?? 0);
                return [
                    'id'       => $module->id,
                    'code'     => $module->code,
                    'name'     => $module->name,
                    'total'    => $total,
                    'approved' => $approved,
                    'reviewed' => $reviewed,
                    'open'     => $total - $approved - $reviewed,
                ];
            })->filter(fn ($m) => $m['total'] > 0)->values();
        }

        // Pages with all 3 roles approved — single aggregation query
        $approvedAll = DB::table('rtmf_frontend_feedbacks')
            ->selectRaw('rtmf_frontend_id')
            ->whereIn('rtmf_frontend_id', $frontendIds)
            ->where('status', 'approved')
            ->whereIn('role', $roles)
            ->groupBy('rtmf_frontend_id')
            ->havingRaw('COUNT(DISTINCT role) = ?', [count($roles)])
            ->get()
            ->count();

        return $this->sendOk([
            'totals' => [
                'frontends'   => $totalFrontends,
                'done'        => $totalDone,
                'modules'     => $totalModules,
                'actors'      => $totalActors,
                'items'       => $totalItems,
                'scenarios'   => $totalScenarios,
                'approvedAll' => $approvedAll,
            ],
            'itemsByStatus' => $itemsByStatus,
            'byModule'      => $byModule,
            'byActor'       => $byActor,
            'byReview'      => $byReview,
            'byRoleModule'  => $byRoleModule,
        ]);
    }

    public function byAssignee(Request $request): JsonResponse
    {
        $projectId = $request->integer('project_id') ?: null;

        $moduleIds   = $projectId
            ? RtmfModule::where('project_id', $projectId)->pluck('id')
            : null;

        $frontendIds = $moduleIds !== null
            ? RtmfFrontend::whereIn('module_id', $moduleIds)->pluck('id')
            : RtmfFrontend::pluck('id');

        // ── 1. Per-assignee breakdown (PHP-side JSON expansion) ──────────────
        $frontends = DB::table('rtmf_frontends')
            ->select('id', 'module_id', 'is_done', 'assignees')
            ->whereIn('id', $frontendIds)
            ->whereNotNull('assignees')
            ->get();

        $moduleLookup = DB::table('rtmf_modules')
            ->select('id', 'code', 'name')
            ->get()->keyBy('id');

        // Pre-build photo lookup: local user id -> photo URL (with testagent fallback)
        $allAssigneeData = [];
        foreach ($frontends as $fe) {
            foreach (json_decode($fe->assignees, true) ?? [] as $a) {
                $allAssigneeData[] = $a;
            }
        }
        $photoByLocalId = $this->resolveAssigneePhotos($allAssigneeData);

        $assigneeMap  = [];
        $feAssigneeKeys = []; // frontend_id => [key, ...]

        foreach ($frontends as $fe) {
            $list = json_decode($fe->assignees, true) ?? [];
            foreach ($list as $a) {
                $email = strtolower(trim($a['email'] ?? ''));
                $key   = $email !== '' ? 'email:' . $email : 'name:' . strtolower(trim($a['name'] ?? 'unknown'));
                $feAssigneeKeys[$fe->id][] = $key;

                if (!isset($assigneeMap[$key])) {
                    $localId   = ($a['source'] ?? 'local') === 'local' ? ($a['id'] ?? null) : null;
                    $photoUrl  = ($localId ? $photoByLocalId[$localId] : null)
                                 ?? $a['photo_url'] ?? $a['photoUrl'] ?? null;

                    $assigneeMap[$key] = [
                        'key'       => $key,
                        'name'      => $a['name'],
                        'email'     => $a['email'] ?? null,
                        'photoUrl'  => $photoUrl,
                        'total'     => 0,
                        'done'      => 0,
                        'byModule'  => [],
                        'baFeedback' => ['open' => 0, 'reviewed' => 0, 'approved' => 0],
                    ];
                }

                $assigneeMap[$key]['total']++;
                if ($fe->is_done) $assigneeMap[$key]['done']++;

                $mid = $fe->module_id;
                if (!isset($assigneeMap[$key]['byModule'][$mid])) {
                    $mod = $moduleLookup[$mid] ?? null;
                    $assigneeMap[$key]['byModule'][$mid] = [
                        'moduleId'   => $mid,
                        'code'       => $mod?->code ?? '?',
                        'name'       => $mod?->name ?? '?',
                        'total'      => 0,
                        'done'       => 0,
                        'baFeedback' => ['approved' => 0, 'reviewed' => 0, 'open' => 0],
                    ];
                }
                $assigneeMap[$key]['byModule'][$mid]['total']++;
                if ($fe->is_done) $assigneeMap[$key]['byModule'][$mid]['done']++;
            }
        }

        // ── 2. BA feedback per assignee (overall + per module) ───────────────
        $baFeedbacks = DB::table('rtmf_frontend_feedbacks')
            ->select('rtmf_frontend_id', 'status')
            ->where('role', 'business_analyst')
            ->whereIn('rtmf_frontend_id', $frontendIds)
            ->get();

        // Build frontend_id => module_id lookup for per-module BA feedback
        $feModuleId = $frontends->pluck('module_id', 'id');

        foreach ($baFeedbacks as $fb) {
            $mid = $feModuleId[$fb->rtmf_frontend_id] ?? null;
            foreach ($feAssigneeKeys[$fb->rtmf_frontend_id] ?? [] as $key) {
                if (isset($assigneeMap[$key])) {
                    $assigneeMap[$key]['baFeedback'][$fb->status]++;
                    if ($mid && isset($assigneeMap[$key]['byModule'][$mid])) {
                        $assigneeMap[$key]['byModule'][$mid]['baFeedback'][$fb->status]++;
                    }
                }
            }
        }

        // Recalculate open = total - approved - reviewed for overall and per-module
        foreach ($assigneeMap as &$a) {
            $a['baFeedback']['open'] = $a['total'] - $a['baFeedback']['approved'] - $a['baFeedback']['reviewed'];
            foreach ($a['byModule'] as &$m) {
                $m['baFeedback']['open'] = $m['total'] - $m['baFeedback']['approved'] - $m['baFeedback']['reviewed'];
            }
            unset($m);
        }
        unset($a);

        // Flatten and sort by total desc
        $assignees = array_values(array_map(function ($a) {
            $a['byModule'] = array_values($a['byModule']);
            return $a;
        }, $assigneeMap));
        usort($assignees, fn ($a, $b) => $b['total'] - $a['total']);

        // ── 3. Daily BA feedback trend (last 14 days) ────────────────────────
        $since = now()->subDays(13)->startOfDay();
        $trendRows = DB::table('rtmf_frontend_feedbacks')
            ->selectRaw("DATE(updated_at) as day, status, COUNT(*) as total")
            ->where('role', 'business_analyst')
            ->whereIn('rtmf_frontend_id', $frontendIds)
            ->where('updated_at', '>=', $since)
            ->groupByRaw("DATE(updated_at), status")
            ->orderBy('day')
            ->get();

        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $days[$d] = ['date' => $d, 'open' => 0, 'reviewed' => 0, 'approved' => 0];
        }
        foreach ($trendRows as $row) {
            if (isset($days[$row->day])) {
                $days[$row->day][$row->status] = (int) $row->total;
            }
        }

        return $this->sendOk([
            'assignees'  => $assignees,
            'dailyTrend' => array_values($days),
        ]);
    }

    /** Returns [localUserId => photoUrl] for all local assignees, with testagent fallback. */
    private function resolveAssigneePhotos(array $assigneeData): array
    {
        $localIds = [];
        foreach ($assigneeData as $a) {
            if (($a['source'] ?? 'local') === 'local' && isset($a['id'])) {
                $localIds[] = (int) $a['id'];
            }
        }
        if (empty($localIds)) return [];

        $localUsers = \App\Models\User::whereIn('id', array_unique($localIds))
            ->get(['id', 'email', 'photo_url'])
            ->keyBy('id');

        $emailsToLookup = $localUsers->filter(fn ($u) => !$u->photo_url)->pluck('email')->values()->toArray();

        $extByEmail = collect();
        if (!empty($emailsToLookup)) {
            try {
                $extByEmail = DB::connection('mysql_external')
                    ->table('User')
                    ->whereIn('email', $emailsToLookup)
                    ->pluck('avatarUrl', 'email');
            } catch (\Throwable) {}
        }

        $result = [];
        foreach ($localUsers as $user) {
            $photo = $user->photo_url ? url($user->photo_url) : ($extByEmail[$user->email] ?? null);
            $result[$user->id] = $photo;
        }
        return $result;
    }
}

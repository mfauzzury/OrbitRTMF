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
            $rows = $feedbackCounts->where('role', $role);
            $byReview[$role] = [
                'approved' => (int) ($rows->where('status', 'approved')->first()?->total ?? 0),
                'reviewed' => (int) ($rows->where('status', 'reviewed')->first()?->total ?? 0),
                'open'     => (int) ($rows->where('status', 'open')->first()?->total ?? 0),
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
                $rows  = $moduleRoleFeedback->where('module_id', $module->id)->where('role', $role);
                $total = (int) ($frontendStats->get($module->id)?->frontends_count ?? 0);
                return [
                    'id'       => $module->id,
                    'code'     => $module->code,
                    'name'     => $module->name,
                    'total'    => $total,
                    'approved' => (int) ($rows->where('status', 'approved')->first()?->total ?? 0),
                    'reviewed' => (int) ($rows->where('status', 'reviewed')->first()?->total ?? 0),
                    'open'     => (int) ($rows->where('status', 'open')->first()?->total ?? 0),
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
}

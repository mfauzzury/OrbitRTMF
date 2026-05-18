<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRtmfFrontendRequest;
use App\Http\Requests\StoreRtmfImportRequest;
use App\Http\Requests\UpdateRtmfFrontendRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\ChecksRtmfProjectRole;
use App\Models\RtmfActor;
use App\Models\RtmfFrontend;
use App\Models\RtmfFrontendApiEndpoint;
use App\Models\RtmfFrontendItem;
use App\Models\RtmfModule;
use App\Models\RtmfSubModule;
use App\Services\VueSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class RtmfFrontendController extends Controller
{
    use ApiResponse, ChecksRtmfProjectRole;

    public function index(Request $request): JsonResponse
    {
        // Headers / page_num survive some proxies that drop or cache the "page" query param
        $page = max(1, (int) (
            $request->header('X-Page-Num')
            ?? $request->query('page_num')
            ?? $request->query('page')
            ?? $request->input('page_num')
            ?? $request->input('page')
            ?? 1
        ));
        $limit = max(1, min(100, (int) (
            $request->header('X-Limit')
            ?? $request->query('limit')
            ?? $request->input('limit')
            ?? 25
        )));
        $q = $request->input('q');
        $moduleId = $request->input('module_id');
        $tabCode = $request->input('tab_code');
        $isDone         = $request->input('is_done');
        $assigneeId       = $request->input('assignee_id');
        $assigneeSource   = $request->input('assignee_source', 'local');
        $assigneeName     = $request->input('assignee_name');
        $assigneeUnassigned = $request->boolean('assignee_unassigned');
        $sortBy         = $request->input('sort_by', 'spec_id');
        $sortDir = $request->input('sort_dir', 'asc');

        $allowedSort = ['spec_id', 'title', 'module_id', 'created_at', 'updated_at'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'spec_id';
        }

        $projectId = $request->integer('project_id') ?: null;

        $query = RtmfFrontend::query()->with([
            'module:id,code,name',
            'subModule:id,module_id,code,name',
            'actors:id,name',
            'feedbacks:id,rtmf_frontend_id,role,status',
        ]);

        if ($projectId) {
            $query->whereHas('module', fn ($q) => $q->where('project_id', $projectId));
        }

        if ($moduleId) {
            $query->where('module_id', $moduleId);
        }

        if ($tabCode) {
            $query->where('tab_code', $tabCode);
        }

        if ($isDone !== null && $isDone !== '') {
            $query->where('is_done', filter_var($isDone, FILTER_VALIDATE_BOOLEAN));
        }

        if ($assigneeUnassigned) {
            $query->where(function ($b) {
                $b->whereNull('assignees')
                  ->orWhereRaw("assignees::jsonb = '[]'::jsonb");
            });
        } elseif ($assigneeId) {
            $query->whereRaw("assignees::jsonb @> ?::jsonb", [json_encode([['id' => (int) $assigneeId, 'source' => $assigneeSource]])]);
        } elseif ($assigneeName) {
            $query->whereRaw("assignees::jsonb @> ?::jsonb", [json_encode([['name' => $assigneeName]])]);
        }

        if ($q) {
            $query->where(function ($b) use ($q) {
                $b->whereRaw('spec_id ilike ?', ["%{$q}%"])
                    ->orWhereRaw('title ilike ?', ["%{$q}%"])
                    ->orWhereRaw('business_requirement ilike ?', ["%{$q}%"]);
            });
        }

        $total = $query->count();

        $rows = $query->orderBy($sortBy, $sortDir)
            ->orderBy('id', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return $this->sendOk($rows, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $total > 0 ? (int) ceil($total / $limit) : 0,
        ]);
    }

    public function store(StoreRtmfFrontendRequest $request): JsonResponse
    {
        $data = $request->validated();
        $module = RtmfModule::find($data['module_id'] ?? null);
        if ($deny = $this->denyIfCannotEdit($request, $module?->project_id)) return $deny;

        $actorIds = $data['actor_ids'] ?? [];
        unset($data['actor_ids']);

        $row = RtmfFrontend::create($data);
        $row->actors()->sync($actorIds);
        $row->load(['module', 'subModule', 'actors']);

        return $this->sendOk($row);
    }

    public function assigneeList(Request $request): JsonResponse
    {
        $projectId = $request->integer('project_id') ?: null;

        $query = RtmfFrontend::query()
            ->whereNotNull('assignees')
            ->whereRaw("assignees::jsonb != '[]'::jsonb");

        if ($projectId) {
            $query->whereHas('module', fn ($q) => $q->where('project_id', $projectId));
        }

        $seen = [];
        $result = [];

        foreach ($query->pluck('assignees') as $assignees) {
            if (!is_array($assignees)) continue;
            foreach ($assignees as $a) {
                $name   = $a['name']   ?? null;
                $source = $a['source'] ?? 'local';
                $id     = $a['id']     ?? null;
                if (!$name) continue;
                $key = $id ? "{$source}:{$id}" : strtolower($name);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $result[] = [
                    'assigneeId'  => $id,
                    'source'      => $source,
                    'name'        => $name,
                    'photoUrl'    => $a['photo_url'] ?? null,
                ];
            }
        }

        usort($result, fn ($a, $b) => strcmp($a['name'], $b['name']));

        // Enrich with live photo URLs
        $localIds    = array_filter(array_column(array_filter($result, fn ($r) => ($r['source'] ?? 'local') === 'local'), 'assigneeId'));
        $externalIds = array_filter(array_column(array_filter($result, fn ($r) => ($r['source'] ?? '') === 'external'), 'assigneeId'));

        $localPhotos = !empty($localIds)
            ? \App\Models\User::whereIn('id', $localIds)->pluck('photo_url', 'id')->map(fn ($p) => $p ? url($p) : null)->toArray()
            : [];

        $externalPhotos = [];
        if (!empty($externalIds)) {
            try {
                $externalPhotos = \Illuminate\Support\Facades\DB::connection('mysql_external')
                    ->table('User')->whereIn('id', $externalIds)->pluck('avatarUrl', 'id')->toArray();
            } catch (\Throwable) {}
        }

        $result = array_map(function ($r) use ($localPhotos, $externalPhotos) {
            $source = $r['source'] ?? 'local';
            $r['photoUrl'] = $source === 'external'
                ? ($externalPhotos[$r['assigneeId']] ?? null)
                : ($localPhotos[$r['assigneeId']] ?? null);
            return $r;
        }, $result);

        return $this->sendOk($result);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $source = RtmfFrontend::with(['actors', 'scenarioGroups.rows'])->find($id);
        if (! $source) {
            return $this->sendError(404, 'NOT_FOUND', 'RTMF frontend not found');
        }

        $module = RtmfModule::find($source->module_id);
        if ($deny = $this->denyIfCannotEdit($request, $module?->project_id)) return $deny;

        $baseSpecId = preg_replace('/_COPY(_\d+)?$/', '', $source->spec_id);
        $baseTitle  = preg_replace('/ \(\d+\)$/', '', $source->title);

        $existingCount = RtmfFrontend::withTrashed()->where('spec_id', 'like', $baseSpecId . '_COPY%')->count();
        $copySpecId = $existingCount === 0
            ? $baseSpecId . '_COPY'
            : $baseSpecId . '_COPY_' . ($existingCount + 1);

        $copy = RtmfFrontend::create([
            'spec_id'                 => $copySpecId,
            'module_id'               => $source->module_id,
            'sub_module_id'           => $source->sub_module_id,
            'tab_code'                => $source->tab_code,
            'vue_path'                => $source->vue_path,
            'url_dev'                 => $source->url_dev,
            'url_stg'                 => $source->url_stg,
            'url_prd'                 => $source->url_prd,
            'title'                   => $baseTitle . ' (' . ($existingCount + 1) . ')',
            'business_requirement'    => $source->business_requirement,
            'stakeholder_requirement' => $source->stakeholder_requirement,
            'description'             => $source->description,
            'is_done'                 => false,
            'assignees'               => $source->assignees,
        ]);

        $copy->actors()->sync($source->actors->pluck('id'));

        foreach (RtmfFrontendItem::where('rtmf_frontend_id', $source->id)->orderBy('sort_order')->get() as $item) {
            RtmfFrontendItem::create([
                'rtmf_frontend_id' => $copy->id,
                'id_fr'            => $item->id_fr,
                'type'             => $item->type,
                'label'            => $item->label,
                'condition'        => $item->condition,
                'validation'       => $item->validation,
                'mandatory'        => $item->mandatory,
                'screen_name'      => $item->screen_name,
                'table_fieldname'  => $item->table_fieldname,
                'status'           => $item->status,
                'sort_order'       => $item->sort_order,
            ]);
        }

        foreach ($source->scenarioGroups as $group) {
            $newGroup = $copy->scenarioGroups()->create([
                'title'       => $group->title,
                'description' => $group->description,
                'sort_order'  => $group->sort_order,
            ]);
            foreach ($group->rows as $row) {
                $newGroup->rows()->create([
                    'step'       => $row->step,
                    'fasa'       => $row->fasa,
                    'role'       => $row->role,
                    'aktiviti'   => $row->aktiviti,
                    'sort_order' => $row->sort_order,
                ]);
            }
        }

        $copy->load(['module', 'subModule', 'actors']);
        return $this->sendCreated($copy);
    }

    public function show(int $id): JsonResponse
    {
        $row = RtmfFrontend::with(['module', 'subModule', 'actors'])->find($id);

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'RTMF frontend not found');
        }

        return $this->sendOk($row);
    }

    public function update(UpdateRtmfFrontendRequest $request, int $id): JsonResponse
    {
        $row = RtmfFrontend::find($id);

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'RTMF frontend not found');
        }

        $module = RtmfModule::find($row->module_id);
        if ($deny = $this->denyIfCannotEdit($request, $module?->project_id)) return $deny;

        $data = $request->validated();
        $hasActors = array_key_exists('actor_ids', $data);
        $actorIds = $data['actor_ids'] ?? [];
        unset($data['actor_ids']);

        $row->update($data);
        if ($hasActors) $row->actors()->sync($actorIds);
        $row->load(['module', 'subModule', 'actors']);

        return $this->sendOk($row);
    }

    public function incomingLinks(int $id): JsonResponse
    {
        $items = RtmfFrontendItem::whereRaw("condition::jsonb @> ?", [json_encode([['p' => $id]])])
            ->select('id', 'rtmf_frontend_id', 'type', 'condition')
            ->with('frontend:id,spec_id,title')
            ->get();

        $result = $items->groupBy('rtmf_frontend_id')->map(function ($grouped) {
            $frontend = $grouped->first()->frontend;
            if (! $frontend) return null;
            return [
                'id'     => $frontend->id,
                'specId' => $frontend->spec_id,
                'title'  => $frontend->title,
                'links'  => $grouped->map(fn ($i) => [
                    'itemId' => $i->id,
                    'type'   => $i->type,
                ])->values(),
            ];
        })->filter()->values();

        return $this->sendOk($result);
    }

    public function allRelations(Request $request): JsonResponse
    {
        $projectId   = $request->integer('project_id') ?: null;
        $moduleIds   = $projectId
            ? RtmfModule::where('project_id', $projectId)->pluck('id')
            : null;
        $frontendIds = $moduleIds !== null
            ? RtmfFrontend::whereIn('module_id', $moduleIds)->pluck('id')
            : RtmfFrontend::pluck('id');

        $items = RtmfFrontendItem::whereIn('rtmf_frontend_id', $frontendIds)
            ->whereNotNull('condition')
            ->where('condition', '!=', '')
            ->where('condition', '!=', '[]')
            ->select('id', 'rtmf_frontend_id', 'type', 'label', 'condition')
            ->with('frontend:id,spec_id,title')
            ->get();

        // Decode condition JSON once per item, then collect target IDs
        $decoded    = [];
        $targetIds  = [];
        foreach ($items as $item) {
            $pairs = json_decode($item->condition, true) ?? [];
            $decoded[$item->id] = $pairs;
            foreach ($pairs as $pair) {
                if (isset($pair['p']) && $pair['p'] > 0) {
                    $targetIds[] = (int) $pair['p'];
                }
            }
        }

        $pageLookup = RtmfFrontend::whereIn('id', array_unique($targetIds))
            ->select('id', 'spec_id', 'title')
            ->get()->keyBy('id');

        $edges = [];
        foreach ($items as $item) {
            foreach ($decoded[$item->id] as $pair) {
                if (!isset($pair['p']) || $pair['p'] <= 0) continue;
                $toPage = $pageLookup->get((int) $pair['p']);
                if (!$toPage) continue;
                $edges[] = [
                    'itemId'    => $item->id,
                    'itemType'  => $item->type,
                    'itemLabel' => $item->label,
                    'condition' => $pair['c'] ?? null,
                    'fromId'    => $item->rtmf_frontend_id,
                    'fromSpecId'=> $item->frontend?->spec_id,
                    'fromTitle' => $item->frontend?->title,
                    'toId'      => (int) $pair['p'],
                    'toSpecId'  => $toPage->spec_id,
                    'toTitle'   => $toPage->title,
                ];
            }
        }

        return $this->sendOk($edges);
    }

    public function source(int $id): JsonResponse
    {
        $row = RtmfFrontend::find($id);

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'RTMF frontend not found');
        }

        $vuePath = $row->vue_path;

        if (! $vuePath) {
            return $this->sendOk([
                'exists' => false,
                'path' => null,
                'content' => null,
                'line_count' => 0,
                'size_bytes' => 0,
            ]);
        }

        $abs = realpath(base_path('../nas-frontend/' . ltrim($vuePath, '/')));
        $root = realpath(base_path('../nas-frontend'));

        if (! $abs || ! $root || ! str_starts_with($abs, $root) || ! is_file($abs) || ! is_readable($abs)) {
            return $this->sendOk([
                'exists' => false,
                'path' => $vuePath,
                'content' => null,
                'line_count' => 0,
                'size_bytes' => 0,
            ]);
        }

        $content = (string) File::get($abs);

        return $this->sendOk([
            'exists' => true,
            'path' => $vuePath,
            'content' => $content,
            'line_count' => substr_count($content, "\n") + 1,
            'size_bytes' => strlen($content),
        ]);
    }

    public function getSnapshot(int $id): JsonResponse
    {
        $row = RtmfFrontend::find($id);

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'RTMF frontend not found');
        }

        return $this->sendOk([
            'html' => $row->snapshot_html,
            'status' => $row->snapshot_status,
            'captured_at' => $row->snapshot_captured_at,
            'vue_path' => $row->vue_path,
            'url_dev' => $row->url_dev,
        ]);
    }

    public function captureSnapshot(int $id, VueSnapshotService $service): JsonResponse
    {
        $row = RtmfFrontend::find($id);

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'RTMF frontend not found');
        }

        if (! $row->vue_path) {
            return $this->sendError(422, 'NO_VUE_PATH', 'Entry has no Vue path set.');
        }

        $result = $service->capture($row->vue_path);

        $row->update([
            'snapshot_html' => $result['html'],
            'snapshot_status' => $result['status'],
            'snapshot_captured_at' => now(),
        ]);

        return $this->sendOk([
            'html' => $row->snapshot_html,
            'status' => $row->snapshot_status,
            'captured_at' => $row->snapshot_captured_at,
            'vue_path' => $row->vue_path,
            'url_dev' => $row->url_dev,
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $moduleId = $request->input('module_id');
        $isDone   = $request->input('is_done');

        $query = RtmfFrontend::with(['module:id,code,name', 'subModule:id,code,name', 'actors:id,name'])
            ->orderBy('spec_id');

        if ($moduleId) {
            $query->where('module_id', $moduleId);
        }
        if ($isDone !== null && $isDone !== '') {
            $query->where('is_done', filter_var($isDone, FILTER_VALIDATE_BOOLEAN));
        }

        $rows = $query->get();

        $filename = 'page-catalog-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Page ID', 'Title', 'Module', 'Sub-module', 'Actors',
                'Vue Path', 'URL Dev', 'URL Staging', 'URL Prod',
                'Done', 'Business Requirement', 'Stakeholder Requirement', 'Description',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->spec_id,
                    $row->title,
                    $row->module?->code . ' — ' . $row->module?->name,
                    $row->subModule?->code,
                    $row->actors->pluck('name')->join(', '),
                    $row->vue_path,
                    $row->url_dev,
                    $row->url_stg,
                    $row->url_prd,
                    $row->is_done ? 'Yes' : 'No',
                    $row->business_requirement,
                    $row->stakeholder_requirement,
                    $row->description,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = RtmfFrontend::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'RTMF frontend not found');
        }

        $module = RtmfModule::find($row->module_id);
        if ($deny = $this->denyIfCannotEdit(request(), $module?->project_id)) return $deny;

        $row->delete();

        return $this->sendOk(['success' => true]);
    }

    public function import(StoreRtmfImportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $module = RtmfModule::firstOrCreate(
            ['code' => $data['module']['code']],
            ['name' => $data['module']['name'], 'sort_order' => $data['module']['sort_order'] ?? 0],
        );

        $subModule = RtmfSubModule::firstOrCreate(
            ['module_id' => $module->id, 'code' => $data['sub_module']['code']],
            ['name' => $data['sub_module']['name'], 'sort_order' => $data['sub_module']['sort_order'] ?? 0],
        );

        $results = [];

        foreach ($data['frontends'] as $i => $fe) {
            $actorIds = [];
            foreach ($fe['actors'] ?? [] as $name) {
                $actorIds[] = RtmfActor::firstOrCreate(['name' => $name])->id;
            }

            $exists = RtmfFrontend::where('spec_id', $fe['spec_id'])->exists();

            $frontend = RtmfFrontend::updateOrCreate(
                ['spec_id' => $fe['spec_id']],
                [
                    'module_id'               => $module->id,
                    'sub_module_id'           => $subModule->id,
                    'tab_code'                => $fe['tab_code'] ?? null,
                    'vue_path'                => $fe['vue_path'] ?? null,
                    'title'                   => $fe['title'],
                    'business_requirement'    => $fe['business_requirement'] ?? null,
                    'stakeholder_requirement' => $fe['stakeholder_requirement'] ?? null,
                    'description'             => $fe['description'] ?? null,
                    'sort_order'              => ($i + 1) * 10,
                ],
            );

            $frontend->actors()->sync($actorIds);

            $itemCount = 0;
            if (! empty($fe['items'])) {
                RtmfFrontendItem::where('rtmf_frontend_id', $frontend->id)->delete();
                foreach ($fe['items'] as $j => $item) {
                    RtmfFrontendItem::create([
                        'rtmf_frontend_id' => $frontend->id,
                        'sort_order'       => $item['sort_order'] ?? $j,
                        'id_fr'            => $item['id_fr'] ?? null,
                        'type'             => $item['type'] ?? null,
                        'label'            => $item['label'] ?? null,
                        'condition'        => $item['condition'] ?? null,
                        'validation'       => $item['validation'] ?? null,
                        'mandatory'        => $item['mandatory'] ?? false,
                        'screen_name'      => $item['screen_name'] ?? null,
                        'table_fieldname'  => $item['table_fieldname'] ?? null,
                        'status'           => $item['status'] ?? 'missing',
                    ]);
                    $itemCount++;
                }
            }

            $endpointCount = 0;
            if (! empty($fe['api_endpoints'])) {
                RtmfFrontendApiEndpoint::where('rtmf_frontend_id', $frontend->id)->delete();
                foreach ($fe['api_endpoints'] as $k => $ep) {
                    RtmfFrontendApiEndpoint::create([
                        'rtmf_frontend_id' => $frontend->id,
                        'method'           => $ep['method'] ?? 'GET',
                        'endpoint'         => $ep['endpoint'] ?? '',
                        'description'      => $ep['description'] ?? null,
                        'sort_order'       => $k,
                    ]);
                    $endpointCount++;
                }
            }

            $results[] = [
                'spec_id'   => $fe['spec_id'],
                'action'    => $exists ? 'updated' : 'created',
                'items'     => $itemCount,
                'endpoints' => $endpointCount,
            ];
        }

        return $this->sendOk([
            'module'     => "{$module->code} — {$module->name}",
            'sub_module' => "{$subModule->code} — {$subModule->name}",
            'frontends'  => $results,
        ]);
    }

    private function enrichAssigneePhotos(\Illuminate\Database\Eloquent\Collection $rows): void
    {
        $localIds    = [];
        $externalIds = [];

        foreach ($rows as $row) {
            foreach ((array) $row->assignees as $a) {
                $source = $a['source'] ?? 'local';
                if ($source === 'local' && isset($a['id'])) {
                    $localIds[] = (int) $a['id'];
                } elseif ($source === 'external' && isset($a['id'])) {
                    $externalIds[] = $a['id'];
                }
            }
        }

        // Local: get photo_url; collect emails of those with null photo for testagent fallback
        $localUsers     = [];
        $emailsToLookup = [];

        if (!empty($localIds)) {
            $localUsers = \App\Models\User::whereIn('id', array_unique($localIds))
                ->get(['id', 'email', 'photo_url'])
                ->keyBy('id');

            foreach ($localUsers as $user) {
                if (!$user->photo_url) {
                    $emailsToLookup[] = $user->email;
                }
            }
        }

        // Testagent: fetch by external IDs + emails of local users missing a photo
        $externalPhotoById    = [];
        $externalPhotoByEmail = [];

        try {
            $query = \Illuminate\Support\Facades\DB::connection('mysql_external')->table('User');

            if (!empty($externalIds) || !empty($emailsToLookup)) {
                $rows2 = $query->where(function ($q) use ($externalIds, $emailsToLookup) {
                    if (!empty($externalIds))   $q->orWhereIn('id', array_unique($externalIds));
                    if (!empty($emailsToLookup)) $q->orWhereIn('email', array_unique($emailsToLookup));
                })->get(['id', 'email', 'avatarUrl']);

                foreach ($rows2 as $u) {
                    $externalPhotoById[$u->id]       = $u->avatarUrl;
                    $externalPhotoByEmail[$u->email] = $u->avatarUrl;
                }
            }
        } catch (\Throwable) {
            // testagent unavailable — skip
        }

        foreach ($rows as $row) {
            $assignees = (array) $row->assignees;
            if (empty($assignees)) continue;

            $updated = array_map(function ($a) use ($localUsers, $externalPhotoById, $externalPhotoByEmail) {
                $source = $a['source'] ?? 'local';

                if ($source === 'local' && isset($a['id'])) {
                    $user  = $localUsers[(int) $a['id']] ?? null;
                    $photo = $user?->photo_url ? url($user->photo_url) : null;
                    // Fallback: look up in testagent by email
                    if (!$photo && $user?->email) {
                        $photo = $externalPhotoByEmail[$user->email] ?? null;
                    }
                    $a['photo_url'] = $photo;
                } elseif ($source === 'external' && isset($a['id'])) {
                    $a['photo_url'] = $externalPhotoById[$a['id']] ?? null;
                }

                return $a;
            }, $assignees);

            $row->assignees = $updated;
        }
    }
}

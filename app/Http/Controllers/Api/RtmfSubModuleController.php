<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderRtmfSubModulesRequest;
use App\Http\Requests\StoreRtmfSubModuleRequest;
use App\Http\Requests\UpdateRtmfSubModuleRequest;
use App\Http\Traits\ApiResponse;
use App\Models\RtmfModule;
use App\Models\RtmfSubModule;
use Illuminate\Http\JsonResponse;

class RtmfSubModuleController extends Controller
{
    use ApiResponse;

    public function index(int $moduleId): JsonResponse
    {
        $module = RtmfModule::find($moduleId);
        if (! $module) {
            return $this->sendError(404, 'NOT_FOUND', 'Module not found');
        }

        return $this->sendOk($module->subModules()->get());
    }

    public function store(StoreRtmfSubModuleRequest $request, int $moduleId): JsonResponse
    {
        $module = RtmfModule::find($moduleId);
        if (! $module) {
            return $this->sendError(404, 'NOT_FOUND', 'Module not found');
        }

        $data = $request->validated();
        $data['sort_order'] = ($module->subModules()
            ->where('parent_id', $data['parent_id'] ?? null)
            ->max('sort_order') ?? 0) + 10;

        $row = $module->subModules()->create($data);

        return $this->sendOk($row);
    }

    public function show(int $moduleId, int $id): JsonResponse
    {
        $row = RtmfSubModule::where('module_id', $moduleId)->find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Sub-module not found');
        }

        return $this->sendOk($row);
    }

    public function update(UpdateRtmfSubModuleRequest $request, int $moduleId, int $id): JsonResponse
    {
        $row = RtmfSubModule::where('module_id', $moduleId)->find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Sub-module not found');
        }
        $row->update($request->validated());

        return $this->sendOk($row);
    }

    public function reorder(ReorderRtmfSubModulesRequest $request, int $moduleId): JsonResponse
    {
        $module = RtmfModule::find($moduleId);
        if (! $module) {
            return $this->sendError(404, 'NOT_FOUND', 'Module not found');
        }

        foreach ($request->input('ids') as $index => $id) {
            RtmfSubModule::where('module_id', $moduleId)
                ->where('id', $id)
                ->update(['sort_order' => ($index + 1) * 10]);
        }

        return $this->sendOk(['success' => true]);
    }

    public function destroy(int $moduleId, int $id): JsonResponse
    {
        $row = RtmfSubModule::where('module_id', $moduleId)->find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Sub-module not found');
        }
        $row->delete();

        return $this->sendOk(['success' => true]);
    }
}

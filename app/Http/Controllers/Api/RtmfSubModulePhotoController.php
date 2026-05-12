<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRtmfSubModulePhotoRequest;
use App\Http\Traits\ApiResponse;
use App\Models\RtmfSubModule;
use App\Models\RtmfSubModulePhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class RtmfSubModulePhotoController extends Controller
{
    use ApiResponse;

    public function index(int $moduleId, int $subModuleId): JsonResponse
    {
        $subModule = RtmfSubModule::where('module_id', $moduleId)->find($subModuleId);
        if (! $subModule) {
            return $this->sendError(404, 'NOT_FOUND', 'Sub-module not found');
        }

        $photos = RtmfSubModulePhoto::where('rtmf_sub_module_id', $subModuleId)
            ->orderBy('created_at')
            ->get();

        return $this->sendOk($photos);
    }

    public function store(StoreRtmfSubModulePhotoRequest $request, int $moduleId, int $subModuleId): JsonResponse
    {
        $subModule = RtmfSubModule::where('module_id', $moduleId)->find($subModuleId);
        if (! $subModule) {
            return $this->sendError(404, 'NOT_FOUND', 'Sub-module not found');
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $safeBase = preg_replace('/-+/', '-', preg_replace('/[^a-z0-9.\-_]/', '-', strtolower($originalName)));
        $ext = pathinfo($safeBase, PATHINFO_EXTENSION);
        $name = pathinfo($safeBase, PATHINFO_FILENAME);
        $filename = $name . '-' . time() . ($ext ? '.' . $ext : '');

        $path = $file->storeAs('rtmf-submodule-photos', $filename, 'public');
        $url = Storage::disk('public')->url('rtmf-submodule-photos/' . $filename);

        $photo = RtmfSubModulePhoto::create([
            'rtmf_sub_module_id' => $subModuleId,
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'path' => $path,
            'url' => $url,
        ]);

        return $this->sendOk($photo);
    }

    public function destroy(int $moduleId, int $subModuleId, int $photoId): JsonResponse
    {
        $photo = RtmfSubModulePhoto::where('rtmf_sub_module_id', $subModuleId)->find($photoId);
        if (! $photo) {
            return $this->sendError(404, 'NOT_FOUND', 'Photo not found');
        }

        Storage::disk('public')->delete('rtmf-submodule-photos/' . $photo->filename);
        $photo->delete();

        return $this->sendOk(['success' => true]);
    }
}

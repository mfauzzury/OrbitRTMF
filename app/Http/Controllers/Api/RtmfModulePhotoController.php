<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRtmfModulePhotoRequest;
use App\Http\Traits\ApiResponse;
use App\Models\RtmfModule;
use App\Models\RtmfModulePhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class RtmfModulePhotoController extends Controller
{
    use ApiResponse;

    public function index(int $moduleId): JsonResponse
    {
        $module = RtmfModule::find($moduleId);
        if (! $module) {
            return $this->sendError(404, 'NOT_FOUND', 'Module not found');
        }

        $photos = RtmfModulePhoto::where('rtmf_module_id', $moduleId)
            ->orderBy('created_at')
            ->get();

        return $this->sendOk($photos);
    }

    public function store(StoreRtmfModulePhotoRequest $request, int $moduleId): JsonResponse
    {
        $module = RtmfModule::find($moduleId);
        if (! $module) {
            return $this->sendError(404, 'NOT_FOUND', 'Module not found');
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $safeBase = preg_replace('/-+/', '-', preg_replace('/[^a-z0-9.\-_]/', '-', strtolower($originalName)));
        $ext = pathinfo($safeBase, PATHINFO_EXTENSION);
        $name = pathinfo($safeBase, PATHINFO_FILENAME);
        $filename = $name . '-' . time() . ($ext ? '.' . $ext : '');

        $path = $file->storeAs('rtmf-module-photos', $filename, 'public');
        $url = Storage::disk('public')->url('rtmf-module-photos/' . $filename);

        $photo = RtmfModulePhoto::create([
            'rtmf_module_id' => $moduleId,
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'path' => $path,
            'url' => $url,
        ]);

        return $this->sendOk($photo);
    }

    public function destroy(int $moduleId, int $photoId): JsonResponse
    {
        $photo = RtmfModulePhoto::where('rtmf_module_id', $moduleId)->find($photoId);
        if (! $photo) {
            return $this->sendError(404, 'NOT_FOUND', 'Photo not found');
        }

        Storage::disk('public')->delete('rtmf-module-photos/' . $photo->filename);
        $photo->delete();

        return $this->sendOk(['success' => true]);
    }
}

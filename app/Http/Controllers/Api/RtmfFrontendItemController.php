<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\RtmfFrontend;
use App\Models\RtmfFrontendItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RtmfFrontendItemController extends Controller
{
    use ApiResponse;

    public function index(int $frontendId): JsonResponse
    {
        if (! RtmfFrontend::find($frontendId)) {
            return $this->sendError(404, 'NOT_FOUND', 'Frontend entry not found');
        }

        $items = RtmfFrontendItem::where('rtmf_frontend_id', $frontendId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->sendOk($items);
    }

    public function store(Request $request, int $frontendId): JsonResponse
    {
        if (! RtmfFrontend::find($frontendId)) {
            return $this->sendError(404, 'NOT_FOUND', 'Frontend entry not found');
        }

        $data = $request->validate([
            'id_fr'          => 'nullable|string|max:32',
            'type'           => 'nullable|string|max:32',
            'label'          => 'nullable|string|max:255',
            'condition'      => 'nullable|string',
            'validation'     => 'nullable|string|max:255',
            'mandatory'      => 'nullable|boolean',
            'screen_name'    => 'nullable|string|max:128',
            'table_fieldname'=> 'nullable|string|max:255',
            'status'         => 'nullable|string|in:implemented,partial,missing',
            'sort_order'     => 'nullable|integer',
        ]);

        $maxSort = RtmfFrontendItem::where('rtmf_frontend_id', $frontendId)->max('sort_order') ?? -1;
        $data['sort_order'] = $data['sort_order'] ?? ($maxSort + 1);

        $item = RtmfFrontendItem::create(['rtmf_frontend_id' => $frontendId] + $data);

        return $this->sendOk($item);
    }

    public function update(Request $request, int $frontendId, int $id): JsonResponse
    {
        $item = RtmfFrontendItem::where('rtmf_frontend_id', $frontendId)->find($id);
        if (! $item) {
            return $this->sendError(404, 'NOT_FOUND', 'Item not found');
        }

        $data = $request->validate([
            'id_fr'          => 'nullable|string|max:32',
            'type'           => 'nullable|string|max:32',
            'label'          => 'nullable|string|max:255',
            'condition'      => 'nullable|string',
            'validation'     => 'nullable|string|max:255',
            'mandatory'      => 'nullable|boolean',
            'screen_name'    => 'nullable|string|max:128',
            'table_fieldname'=> 'nullable|string|max:255',
            'status'         => 'nullable|string|in:implemented,partial,missing',
            'sort_order'     => 'nullable|integer',
        ]);

        $item->update($data);

        return $this->sendOk($item);
    }

    public function destroy(int $frontendId, int $id): JsonResponse
    {
        $item = RtmfFrontendItem::where('rtmf_frontend_id', $frontendId)->find($id);
        if (! $item) {
            return $this->sendError(404, 'NOT_FOUND', 'Item not found');
        }

        $item->delete();

        return $this->sendOk(['success' => true]);
    }
}

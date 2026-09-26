<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\Api\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EquipmentStoreRequest;
use App\Http\Requests\Admin\EquipmentUpdateRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Services\Admin\EquipmentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EquipmentController extends Controller
{
    public function __construct(private readonly EquipmentServiceInterface $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Equipment::class);

        $equipment = Equipment::query()
            ->withCount('exercises')
            ->orderBy('id')
            ->get();

        return EquipmentResource::collection($equipment);
    }

    public function show(Equipment $equipment): EquipmentResource
    {
        $this->authorize('viewAny', Equipment::class);

        return new EquipmentResource($equipment);
    }

    public function store(EquipmentStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Equipment::class);

        $equipment = $this->service->create($request->validated());

        return (new EquipmentResource($equipment))->response()->setStatusCode(201);
    }

    public function update(EquipmentUpdateRequest $request, Equipment $equipment): EquipmentResource
    {
        $this->authorize('update', $equipment);

        $equipment = $this->service->update($equipment, $request->validated());

        return new EquipmentResource($equipment);
    }

    public function destroy(Equipment $equipment): Response
    {
        $this->authorize('delete', $equipment);

        if ($equipment->exercises()->withTrashed()->exists()) {
            throw new ApiException(409, 'in_use', __('Cannot delete equipment that is used by exercises.'));
        }

        $this->service->delete($equipment);

        return response()->noContent();
    }
}

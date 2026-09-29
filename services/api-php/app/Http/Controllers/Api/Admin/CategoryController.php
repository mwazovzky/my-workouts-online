<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryStoreRequest;
use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Http\Resources\AdminCategoryResource;
use App\Models\Category;
use App\Services\Admin\CategoryServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryServiceInterface $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->withCount('exercises')
            ->orderBy('id')
            ->get();

        return AdminCategoryResource::collection($categories);
    }

    public function show(Category $category): AdminCategoryResource
    {
        $this->authorize('viewAny', Category::class);

        return new AdminCategoryResource($category);
    }

    public function store(CategoryStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->service->create($request->validated());

        return (new AdminCategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(CategoryUpdateRequest $request, Category $category): AdminCategoryResource
    {
        $this->authorize('update', $category);

        $category = $this->service->update($category, $request->validated());

        return new AdminCategoryResource($category);
    }

    public function destroy(Category $category): Response
    {
        $this->authorize('delete', $category);

        $this->service->delete($category);

        return response()->noContent();
    }
}

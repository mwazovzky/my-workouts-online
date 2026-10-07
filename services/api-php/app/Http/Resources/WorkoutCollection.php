<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Paginated workout history with exactly the `links` and `meta` keys the
 * contract's `WorkoutPage` describes.
 */
class WorkoutCollection extends ResourceCollection
{
    public $collects = WorkoutResource::class;

    /**
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, array<string, mixed>>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'links' => [
                'first' => $paginated['first_page_url'],
                'last' => $paginated['last_page_url'],
                'prev' => $paginated['prev_page_url'],
                'next' => $paginated['next_page_url'],
            ],
            'meta' => [
                'current_page' => $paginated['current_page'],
                'from' => $paginated['from'],
                'last_page' => $paginated['last_page'],
                'per_page' => $paginated['per_page'],
                'to' => $paginated['to'],
                'total' => $paginated['total'],
            ],
        ];
    }
}

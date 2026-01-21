<?php

namespace App\Services\Activity;

use App\Models\Activity;
use App\Models\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivityService
{
    /**
     * Update an Activity and its sets.
     *
     * @param  array{sets?: array<int, array{id?: int|null, order: int, repetitions: int, weight: numeric}>}  $data
     */
    public function update(Activity $activity, array $data): Activity
    {
        $normalizedSets = $this->normalizeSetsPayload($data['sets'] ?? []);

        DB::transaction(function () use ($activity, $normalizedSets) {
            $payloadSetIds = $this->extractExistingSetIds($normalizedSets);

            $this->assertSetIdsBelongToActivity($activity, $payloadSetIds);
            $this->deleteSetsNotInPayload($activity, $payloadSetIds);
            $this->clearOrdersForExistingSets($activity, $payloadSetIds);
            $this->upsertSets($activity, $normalizedSets);
        });

        return $activity->load(['sets' => fn ($query) => $query->orderBy('order')]);
    }

    /**
     * Normalize the sets payload and recalculate orders to be sequential starting from 1.
     *
     * @param  array<int, mixed>  $sets
     * @return array<int, array{id: int|null, order: int, repetitions: int, weight: numeric}>
     */
    private function normalizeSetsPayload(array $sets): array
    {
        return collect($sets)
            ->sortBy('order')
            ->values()
            ->map(fn (array $set, int $index) => [
                'id' => isset($set['id']) && $set['id'] !== null ? (int) $set['id'] : null,
                'order' => $index + 1,
                'repetitions' => (int) $set['repetitions'],
                'weight' => $set['weight'],
            ])
            ->all();
    }

    /**
     * @param  array<int, array{id: int|null, order: int, repetitions: int, weight: numeric}>  $sets
     * @return array<int, int>
     */
    private function extractExistingSetIds(array $sets): array
    {
        return collect($sets)
            ->pluck('id')
            ->filter(fn ($id) => $id !== null)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $setIds
     */
    private function assertSetIdsBelongToActivity(Activity $activity, array $setIds): void
    {
        if (empty($setIds)) {
            return;
        }

        $validCount = Set::query()
            ->where('activity_id', $activity->id)
            ->whereIn('id', $setIds)
            ->count();

        if ($validCount !== count($setIds)) {
            throw ValidationException::withMessages([
                'sets' => 'One or more sets do not belong to this activity.',
            ]);
        }
    }

    /**
     * @param  array<int, int>  $keepSetIds
     */
    private function deleteSetsNotInPayload(Activity $activity, array $keepSetIds): void
    {
        $query = Set::query()->where('activity_id', $activity->id);

        if (empty($keepSetIds)) {
            $query->delete();
            return;
        }

        $query->whereNotIn('id', $keepSetIds)->delete();
    }

    /**
     * Clear orders for existing sets in a single query to avoid unique constraint violations.
     *
     * @param  array<int, int>  $setIds
     */
    private function clearOrdersForExistingSets(Activity $activity, array $setIds): void
    {
        if (empty($setIds)) {
            return;
        }

        Set::query()
            ->where('activity_id', $activity->id)
            ->whereIn('id', $setIds)
            ->update(['order' => DB::raw('-id')]);
    }

    /**
     * Upsert sets using bulk operations for better performance.
     *
     * @param  array<int, array{id: int|null, order: int, repetitions: int, weight: numeric}>  $sets
     */
    private function upsertSets(Activity $activity, array $sets): void
    {
        [$updates, $creates] = collect($sets)->partition(fn ($set) => $set['id'] !== null);

        // Bulk update existing sets using upsert
        if ($updates->isNotEmpty()) {
            Set::upsert(
                $updates->map(fn ($set) => [
                    'id' => $set['id'],
                    'activity_id' => $activity->id,
                    'order' => $set['order'],
                    'repetitions' => $set['repetitions'],
                    'weight' => $set['weight'],
                ])->all(),
                uniqueBy: ['id'],
                update: ['order', 'repetitions', 'weight']
            );
        }

        // Bulk create new sets
        if ($creates->isNotEmpty()) {
            $activity->sets()->createMany(
                $creates->map(fn ($set) => [
                    'order' => $set['order'],
                    'repetitions' => $set['repetitions'],
                    'weight' => $set['weight'],
                ])->all()
            );
        }
    }
}

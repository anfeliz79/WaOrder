<?php

namespace App\Services\Branch;

use App\Models\Branch;

class BranchRouter
{
    /**
     * Find the nearest active branch that can deliver to the given coordinates.
     */
    public function findNearestBranch(int $tenantId, float $lat, float $lng): ?Branch
    {
        $branches = Branch::where('tenant_id', $tenantId)
            ->active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($branches as $branch) {
            $distance = $branch->distanceTo($lat, $lng);

            if ($distance <= $branch->max_delivery_distance_km && $distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $branch;
            }
        }

        return $nearest;
    }

    /**
     * Get the default (first active) branch for a tenant.
     */
    public function getDefaultBranch(int $tenantId): ?Branch
    {
        return Branch::where('tenant_id', $tenantId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * Get all active branches sorted by distance from coordinates.
     * Returns array of ['branch' => Branch, 'distance' => float|null].
     */
    public function getAllSortedByDistance(int $tenantId, float $lat, float $lng): array
    {
        $branches = Branch::where('tenant_id', $tenantId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $withDistance = [];
        foreach ($branches as $branch) {
            $distance = ($branch->latitude && $branch->longitude)
                ? $branch->distanceTo($lat, $lng)
                : null;
            $withDistance[] = ['branch' => $branch, 'distance' => $distance];
        }

        usort($withDistance, function ($a, $b) {
            if ($a['distance'] === null && $b['distance'] === null) return 0;
            if ($a['distance'] === null) return 1;
            if ($b['distance'] === null) return -1;
            return $a['distance'] <=> $b['distance'];
        });

        return $withDistance;
    }

    /**
     * Get all active branches in default sort order.
     * Returns array of ['branch' => Branch, 'distance' => null].
     */
    public function getAllBranches(int $tenantId): array
    {
        return Branch::where('tenant_id', $tenantId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($b) => ['branch' => $b, 'distance' => null])
            ->toArray();
    }
}

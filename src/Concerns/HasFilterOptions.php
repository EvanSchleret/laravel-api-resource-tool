<?php

namespace EvanSchleret\LaravelApiResourceTool\Concerns;

use EvanSchleret\LaravelApiResourceTool\Scopes\FilterScope;
use EvanSchleret\LaravelApiResourceTool\Scopes\SearchScope;
use EvanSchleret\LaravelApiResourceTool\Scopes\SortScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

trait HasFilterOptions
{
    public function scopeFilterOptions(Builder $query, array $options = []): Builder
    {
        if (! empty($options['filter'])) {
            $this->addResourceFilterScope($query, $options['authorized_filters'] ?? []);
        }

        if (! empty($options['search_columns'])) {
            $query->withGlobalScope('search', new SearchScope(
                columns: $options['search_columns'],
            ));
        }

        $query->withGlobalScope('sort', new SortScope($options['sortable_columns'] ?? null));

        return $query;
    }

    public function scopeFilter(Builder $query, array $options): Builder
    {
        $this->addResourceFilterScope($query, $options);

        $query->withGlobalScope('sort', new SortScope);

        return $query;
    }

    public function scopeSearch(Builder $query, array $options): Builder
    {
        $query->withGlobalScope('search', new SearchScope(
            columns: $options,
        ));

        $query->withGlobalScope('sort', new SortScope);

        return $query;
    }

    public function scopeSort(Builder $query, array $sortableColumns): Builder
    {
        $query->withGlobalScope('sort', new SortScope($sortableColumns));

        return $query;
    }

    private function addResourceFilterScope(Builder $query, array $authorizedFilters): void
    {
        $scope = new FilterScope(authorized_filters: $authorizedFilters);

        if ($scope->includesTrashed()) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        $query->withGlobalScope('filter', $scope);
    }
}

<?php

namespace EvanSchleret\LaravelApiResourceTool\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Collection;

class FilterScope implements Scope
{
    private ?string $filter;

    private Collection $simpleFilters;

    private Collection $nestedFilters;

    private Collection $authorizedRelationLengths;

    private bool $includeTrashed = false;

    public function __construct(array $authorized_filters)
    {
        $userFilters = $this->decodeFilters(request()->input('filter', '[]'));
        $defaultFilters = $this->decodeFilters(request()->input('defaultFilter', '[]'));

        $this->filter = collect($userFilters)
            ->concat($defaultFilters)
            ->filter()
            ->values()
            ->toJson();

        $this->simpleFilters = collect();
        $this->nestedFilters = collect();
        $this->authorizedRelationLengths = collect();

        foreach ($authorized_filters as $key => $value) {
            if (is_int($key)) {
                $this->simpleFilters->push($value);

                continue;
            }

            if (! is_string($key) || ! is_array($value)) {
                continue;
            }

            $segments = explode('.', $key);
            if (count($segments) < 2) {
                continue;
            }

            $field = array_pop($segments);
            $relationPath = implode('.', $segments);

            if ($field === 'length') {
                $this->authorizedRelationLengths->push($relationPath);
                $this->nestedFilters->put($relationPath, collect($value));

                continue;
            }

            $this->nestedFilters->put($relationPath, collect($value)->map(fn ($column) => (string) $column));
        }

        $this->includeTrashed = $this->simpleFilters->contains('trashed')
            && collect(json_decode($this->filter) ?: [])->contains(function ($filter): bool {
                return is_object($filter)
                    && isset($filter->key, $filter->operator)
                    && property_exists($filter, 'value')
                    && $filter->key === 'trashed'
                    && $this->normalizeOperator($filter->operator) === '='
                    && $this->castValue($filter->value) === true;
            });
    }

    public function includesTrashed(): bool
    {
        return $this->includeTrashed;
    }

    public function apply(Builder $builder, Model $model): void
    {
        if (is_null($this->filter)) {
            return;
        }

        $filters = json_decode($this->filter);
        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $filter) {
            if (
                ! is_object($filter) ||
                ! isset($filter->key, $filter->operator) ||
                ! is_string($filter->key) ||
                $filter->key === '' ||
                ! property_exists($filter, 'value')
            ) {
                continue;
            }

            $operator = $this->normalizeOperator($filter->operator);
            if ($operator === null) {
                continue;
            }

            if ($filter->key === 'trashed') {
                if (! $this->simpleFilters->contains('trashed') || $operator !== '=') {
                    continue;
                }

                if ($this->includeTrashed) {
                    continue;
                }

                if ($this->castValue($filter->value) === false) {
                    $builder->whereNull('deleted_at');
                }

                continue;
            }

            if (str_contains($filter->key, '.')) {
                $segments = explode('.', $filter->key);
                $last = array_pop($segments);
                $relationPath = implode('.', $segments);

                if ($last === 'length') {
                    if (! $this->authorizedRelationLengths->contains($relationPath)) {
                        continue;
                    }

                    if (! $this->relationPathExists($builder->getModel(), $relationPath)) {
                        throw new \InvalidArgumentException("Relation {$relationPath} does not exist on model ".get_class($builder->getModel()));
                    }

                    if (is_object($filter->value)) {
                        $nestedOperator = $this->normalizeOperator($filter->value->operator ?? null);
                        if (
                            ! isset($filter->value->column) ||
                            ! property_exists($filter->value, 'value') ||
                            ! is_string($filter->value->column) ||
                            $nestedOperator === null ||
                            ! $this->nestedFilters->get($relationPath, collect())->contains($filter->value->column) ||
                            in_array($nestedOperator, ['in', 'not in'], true) ||
                            is_array($filter->value->value) ||
                            is_object($filter->value->value)
                        ) {
                            continue;
                        }

                        $value = $filter->value->value === 'now()' ? now() : $this->castValue($filter->value->value);
                        $countAlias = str_replace('.', '_', $relationPath).'_count';

                        $builder->withCount([
                            $relationPath => function (Builder $query) use ($filter, $value, $nestedOperator) {
                                $this->applyCondition($query, $filter->value->column, $nestedOperator, $value);
                            },
                        ]);

                        $builder->having($countAlias, $operator, 1);
                    } else {
                        $value = $filter->value;
                        $isInteger = is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false);
                        if (
                            ! $isInteger ||
                            in_array($operator, ['in', 'not in', 'like', 'not like'], true)
                        ) {
                            continue;
                        }

                        $builder->has($relationPath, $operator, (int) $value);
                    }

                    continue;
                }

                if ($this->relationPathExists($builder->getModel(), $relationPath)) {
                    $field = $last;
                    if (
                        ! $this->simpleFilters->contains($filter->key) &&
                        (! $this->nestedFilters->has($relationPath) || ! $this->nestedFilters->get($relationPath)->contains($field))
                    ) {
                        continue;
                    }

                    if (is_object($filter->value)) {
                        continue;
                    }

                    $relatedTable = $this->resolveRelationTable($builder->getModel(), $relationPath);
                    $qualified = ($relatedTable ? ($relatedTable.'.') : '').$field;

                    $builder->whereHas($relationPath, function (Builder $query) use ($qualified, $operator, $filter) {
                        $this->applyCondition($query, $qualified, $operator, $this->castValue($filter->value));
                    });

                    continue;
                }

                $column = array_shift($segments);
                $jsonPathSegments = array_merge($segments, [$last]);
                $jsonKey = $column.(count($jsonPathSegments) ? ('->'.implode('->', $jsonPathSegments)) : '');

                if (! $this->simpleFilters->contains($filter->key)) {
                    continue;
                }

                $this->applyCondition($builder, $jsonKey, $operator, $this->castValue($filter->value));
            } else {
                if (is_object($filter->value) || ! $this->simpleFilters->contains($filter->key)) {
                    continue;
                }

                $this->applyCondition($builder, $filter->key, $operator, $this->castValue($filter->value));
            }
        }
    }

    private function decodeFilters(mixed $filters): array
    {
        if (! is_string($filters)) {
            return [];
        }

        $decoded = json_decode($filters);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeOperator(mixed $operator): ?string
    {
        if (! is_string($operator)) {
            return null;
        }

        $normalized = strtolower(trim($operator));

        return match ($normalized) {
            '=', 'like', 'not like', 'in', 'not in', '<', '<=', '>', '>=' => $normalized,
            '==', '===' => '=',
            '!=', '!==', '<>' => '!=',
            default => null,
        };
    }

    private function applyCondition(Builder $builder, string $column, string $operator, mixed $value): void
    {
        if ($operator === 'in' || $operator === 'not in') {
            if (! is_array($value) || $value === []) {
                return;
            }

            if ($operator === 'in') {
                $builder->whereIn($column, $value);
            } else {
                $builder->whereNotIn($column, $value);
            }

            return;
        }

        if (is_array($value) || (is_object($value) && ! $value instanceof \DateTimeInterface) || $value === '') {
            return;
        }

        if ($value === null) {
            if ($operator === '=') {
                $builder->whereNull($column);
            } elseif ($operator === '!=') {
                $builder->whereNotNull($column);
            }

            return;
        }

        $builder->where($column, $operator, $value);
    }

    private function castValue(mixed $value): bool|array|string|null
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $stringValue = (string) $value;

        return match (strtolower($stringValue)) {
            'null' => null,
            'true', '1', 'yes', 'on' => true,
            'false', '0', 'no', 'off' => false,
            default => $stringValue,
        };
    }

    private function relationPathExists(Model $model, string $relationPath): bool
    {
        $segments = explode('.', $relationPath);
        $current = $model;

        foreach ($segments as $segment) {
            if (! method_exists($current, $segment)) {
                return false;
            }

            try {
                $relation = $current->{$segment}();
            } catch (\Throwable $e) {
                return false;
            }

            if (! $relation instanceof Relation) {
                return false;
            }

            $current = $relation->getRelated();
        }

        return true;
    }

    private function resolveRelationTable(Model $model, string $relationPath): ?string
    {
        $segments = explode('.', $relationPath);
        $current = $model;

        foreach ($segments as $segment) {
            if (! method_exists($current, $segment)) {
                return null;
            }
            try {
                $relation = $current->{$segment}();
            } catch (\Throwable $e) {
                return null;
            }
            if (! $relation instanceof Relation) {
                return null;
            }
            if ($relation instanceof MorphTo) {
                return null;
            }
            $current = $relation->getRelated();
        }

        return method_exists($current, 'getTable') ? $current->getTable() : null;
    }
}

<?php

namespace EvanSchleret\LaravelApiResourceTool\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Scope;

class SearchScope implements Scope
{
    private string $search;

    private static array $columnExistsCache = [];

    public function __construct(private array $columns)
    {
        $search = request()->input('search');
        $this->search = is_string($search) ? $search : '';
    }

    public function apply(Builder $builder, Model $model): void
    {
        if (empty($this->search)) {
            return;
        }

        $builder->where(function (Builder $query) {
            foreach ($this->columns as $column) {
                if (! is_string($column) || $column === '') {
                    continue;
                }

                if (str_contains($column, '.')) {
                    $parts = explode('.', $column);
                    $field = array_pop($parts);
                    $relationPath = implode('.', $parts);
                    $relatedModel = $this->resolveRelatedModel($query->getModel(), $relationPath);

                    if (! $relatedModel || ! $this->tableHasColumn($relatedModel, $field)) {
                        continue;
                    }

                    $relatedTable = $relatedModel->getTable();
                    $query->orWhereHas($relationPath, function (Builder $relationQuery) use ($field, $relatedTable) {
                        $qualified = $relatedTable.'.'.$field;
                        $relationQuery->where($qualified, 'like', '%'.$this->search.'%');
                    });

                    continue;
                }

                $model = $query->getModel();
                $cast = $model->getCasts()[$column] ?? null;

                if (is_string($cast) && enum_exists($cast)) {
                    foreach ($cast::cases() as $case) {
                        if (str_contains(strtolower($case->name), strtolower($this->search)) || str_contains(strtolower((string) $case->value), strtolower($this->search))) {
                            $query->orWhere($column, $case);
                        }
                    }
                } elseif ($this->tableHasColumn($model, $column)) {
                    $query->orWhere($column, 'like', '%'.$this->search.'%');
                }
            }
        });
    }

    private function resolveRelatedModel(Model $model, string $relationPath): ?Model
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

            if (! $relation instanceof Relation || $relation instanceof MorphTo) {
                return null;
            }

            $current = $relation->getRelated();
        }

        return $current;
    }

    private function tableHasColumn(Model $model, string $column): bool
    {
        $connection = $model->getConnection();
        $cacheKey = serialize([
            $connection->getName(),
            $connection->getDatabaseName(),
            $connection->getConfig('schema'),
            $connection->getConfig('search_path'),
            $model->getTable(),
            $column,
        ]);

        if (! array_key_exists($cacheKey, self::$columnExistsCache)) {
            self::$columnExistsCache[$cacheKey] = $connection->getSchemaBuilder()->hasColumn($model->getTable(), $column);
        }

        return self::$columnExistsCache[$cacheKey];
    }
}

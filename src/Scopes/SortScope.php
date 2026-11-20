<?php

namespace EvanSchleret\LaravelApiResourceTool\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SortScope implements Scope
{
    private static array $columnListingCache = [];

    public function __construct(private ?array $sortableColumns = null) {}

    public function apply(Builder $builder, Model $model): void
    {
        $sorts = $this->requestedSorts();

        if ($sorts === []) {
            return;
        }

        $connection = $model->getConnection();
        $cacheKey = serialize([
            $connection->getName(),
            $connection->getDatabaseName(),
            $connection->getConfig('schema'),
            $connection->getConfig('search_path'),
            $model->getTable(),
        ]);
        $columns = self::$columnListingCache[$cacheKey] ??= $connection
            ->getSchemaBuilder()
            ->getColumnListing($model->getTable());

        foreach ($sorts as [$column, $order]) {
            if (
                ! in_array($column, $columns, true) ||
                ($this->sortableColumns !== null && ! in_array($column, $this->sortableColumns, true))
            ) {
                continue;
            }

            $this->applySort($builder, $model, $column, $order);
        }
    }

    private function requestedSorts(): array
    {
        $sort = request()->input('sort');

        if (is_string($sort) && trim($sort) !== '') {
            $sorts = [];

            foreach (explode(',', $sort) as $field) {
                $field = trim($field);
                $descending = str_starts_with($field, '-');
                $column = $descending ? substr($field, 1) : $field;

                if ($column !== '') {
                    $sorts[] = [$column, $descending ? 'desc' : 'asc'];
                }
            }

            return $sorts;
        }

        $orderBy = request()->input('orderBy');

        if (! is_string($orderBy) || $orderBy === '') {
            return [];
        }

        return [[
            $orderBy,
            request()->boolean('desc') ? 'desc' : 'asc',
        ]];
    }

    private function applySort(Builder $builder, Model $model, string $column, string $order): void
    {
        $cast = $model->getCasts()[$column] ?? null;

        if (is_string($cast) && enum_exists($cast)) {
            $values = array_map(fn ($e) => $e->value, $cast::cases());
            $values = $order === 'desc' ? array_reverse($values) : $values;
            $query = $builder->getQuery();

            if (method_exists($query, 'inOrderOf')) {
                $query->inOrderOf($column, $values);
            } else {
                $wrappedColumn = $query->getGrammar()->wrap($column);
                $cases = [];

                foreach ($values as $index => $value) {
                    $cases[] = "when {$wrappedColumn} = ? then {$index}";
                }

                $query->orderByRaw(
                    'case '.implode(' ', $cases).' else '.count($values).' end',
                    $values,
                );
            }

            return;
        }

        $builder->orderBy($column, $order);
    }
}

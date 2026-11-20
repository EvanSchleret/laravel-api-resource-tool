<?php

namespace Tests\Feature;

use Tests\Enums\EntryStatus;
use Tests\Models\Author;
use Tests\Models\Entry;
use Tests\TestCase;

class SearchAndSortTest extends TestCase
{
    public function test_it_searches_authorized_columns(): void
    {
        Entry::query()->create(['name' => 'Alpha']);
        Entry::query()->create(['name' => 'Beta']);
        $this->setQueryParameters(['search' => 'Alpha']);

        $names = Entry::query()->search(['name'])->pluck('name')->all();

        $this->assertSame(['Alpha'], $names);
    }

    public function test_it_ignores_non_string_search_input(): void
    {
        Entry::query()->create(['name' => 'Alpha']);
        $this->setQueryParameters(['search' => ['Alpha']]);

        $this->assertSame(['Alpha'], Entry::query()->search(['name'])->pluck('name')->all());
    }

    public function test_it_searches_related_columns(): void
    {
        $author = Author::query()->create(['name' => 'Ada Lovelace']);
        Entry::query()->create(['author_id' => $author->id, 'name' => 'First']);
        Entry::query()->create(['name' => 'Second']);
        $this->setQueryParameters(['search' => 'Lovelace']);

        $names = Entry::query()->search(['author.name'])->pluck('name')->all();

        $this->assertSame(['First'], $names);
    }

    public function test_it_sorts_enum_values_in_declared_order(): void
    {
        Entry::query()->create(['name' => 'Archived', 'status' => EntryStatus::Archived]);
        Entry::query()->create(['name' => 'Published', 'status' => EntryStatus::Published]);
        Entry::query()->create(['name' => 'Draft', 'status' => EntryStatus::Draft]);
        $this->setQueryParameters(['orderBy' => 'status']);

        $statuses = Entry::query()->search(['name'])->get()->map(fn (Entry $entry) => $entry->status->value)->all();

        $this->assertSame(['draft', 'published', 'archived'], $statuses);
    }

    public function test_it_reverses_enum_order_when_descending(): void
    {
        Entry::query()->create(['name' => 'Archived', 'status' => EntryStatus::Archived]);
        Entry::query()->create(['name' => 'Published', 'status' => EntryStatus::Published]);
        Entry::query()->create(['name' => 'Draft', 'status' => EntryStatus::Draft]);
        $this->setQueryParameters(['orderBy' => 'status', 'desc' => '1']);

        $statuses = Entry::query()->search(['name'])->get()->map(fn (Entry $entry) => $entry->status->value)->all();

        $this->assertSame(['archived', 'published', 'draft'], $statuses);
    }

    public function test_it_applies_multiple_sorts_and_respects_the_sortable_column_list(): void
    {
        Entry::query()->create([
            'name' => 'Zulu',
            'created_at' => '2025-01-02 00:00:00',
            'updated_at' => '2025-01-02 00:00:00',
        ]);
        Entry::query()->create([
            'name' => 'Alpha',
            'created_at' => '2025-01-02 00:00:00',
            'updated_at' => '2025-01-02 00:00:00',
        ]);
        Entry::query()->create([
            'name' => 'Middle',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ]);
        $this->setQueryParameters(['sort' => '-created_at,name']);

        $sortedNames = Entry::query()
            ->filterOptions(['sortable_columns' => ['created_at', 'name']])
            ->pluck('name')
            ->all();
        $restrictedNames = Entry::query()
            ->search(['name'])
            ->sort(['name'])
            ->pluck('name')
            ->all();

        $this->assertSame(['Alpha', 'Zulu', 'Middle'], $sortedNames);
        $this->assertSame(['Alpha', 'Middle', 'Zulu'], $restrictedNames);
    }
}

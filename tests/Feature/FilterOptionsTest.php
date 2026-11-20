<?php

namespace Tests\Feature;

use Tests\Models\Author;
use Tests\Models\Comment;
use Tests\Models\Entry;
use Tests\TestCase;

class FilterOptionsTest extends TestCase
{
    public function test_it_applies_an_authorized_simple_filter(): void
    {
        Entry::query()->create(['name' => 'Draft', 'status' => 'draft']);
        Entry::query()->create(['name' => 'Published', 'status' => 'published']);
        $this->setFilters([['key' => 'status', 'operator' => '=', 'value' => 'published']]);

        $names = Entry::query()->filter(['status'])->pluck('name')->all();

        $this->assertSame(['Published'], $names);
    }

    public function test_it_ignores_a_filter_that_is_not_authorized(): void
    {
        Entry::query()->create(['name' => 'First', 'status' => 'draft']);
        Entry::query()->create(['name' => 'Second', 'status' => 'published']);
        $this->setFilters([['key' => 'status', 'operator' => '=', 'value' => 'published']]);

        $this->assertCount(2, Entry::query()->filter(['name'])->get());
    }

    public function test_it_applies_in_filters_with_array_values(): void
    {
        Entry::query()->create(['name' => 'Draft', 'status' => 'draft']);
        Entry::query()->create(['name' => 'Published', 'status' => 'published']);
        Entry::query()->create(['name' => 'Archived', 'status' => 'archived']);
        $this->setFilters([['key' => 'status', 'operator' => 'in', 'value' => ['draft', 'archived']]]);

        $names = Entry::query()->filter(['status'])->orderBy('name')->pluck('name')->all();

        $this->assertSame(['Archived', 'Draft'], $names);
    }

    public function test_it_handles_json_null_and_boolean_false_values(): void
    {
        Entry::query()->create(['name' => 'Match', 'nickname' => null, 'is_active' => false]);
        Entry::query()->create(['name' => 'No match', 'nickname' => 'Ari', 'is_active' => false]);
        Entry::query()->create(['name' => 'Also no match', 'nickname' => null, 'is_active' => true]);
        $this->setFilters([
            ['key' => 'nickname', 'operator' => '=', 'value' => null],
            ['key' => 'is_active', 'operator' => '=', 'value' => false],
        ]);

        $names = Entry::query()->filter(['nickname', 'is_active'])->pluck('name')->all();

        $this->assertSame(['Match'], $names);
    }

    public function test_it_filters_related_fields_when_authorized(): void
    {
        $author = Author::query()->create(['name' => 'Ada Lovelace']);
        Entry::query()->create(['author_id' => $author->id, 'name' => 'Analytical engine']);
        Entry::query()->create(['name' => 'Other entry']);
        $this->setFilters([['key' => 'author.name', 'operator' => 'like', 'value' => 'Ada%']]);

        $names = Entry::query()->filter(['author.name'])->pluck('name')->all();

        $this->assertSame(['Analytical engine'], $names);
    }

    public function test_it_requires_explicit_authorization_for_relation_lengths(): void
    {
        $withComments = Entry::query()->create(['name' => 'With comments']);
        Entry::query()->create(['name' => 'Without comments']);
        Comment::query()->create(['entry_id' => $withComments->id]);
        $this->setFilters([['key' => 'comments.length', 'operator' => '>=', 'value' => 1]]);

        $unauthorizedCount = Entry::query()->filter(['name'])->count();
        $authorizedCount = Entry::query()->filter(['comments.length' => []])->count();

        $this->assertSame(2, $unauthorizedCount);
        $this->assertSame(1, $authorizedCount);
    }

    public function test_trashed_filter_keeps_other_query_constraints(): void
    {
        $deletedEntry = Entry::query()->create(['tenant_id' => 1, 'name' => 'Deleted']);
        $deletedEntry->delete();
        Entry::query()->create(['tenant_id' => 1, 'name' => 'Active']);
        Entry::query()->create(['tenant_id' => 2, 'name' => 'Another tenant']);
        $this->setFilters([['key' => 'trashed', 'operator' => '=', 'value' => true]]);

        $names = Entry::query()->where('tenant_id', 1)->filter(['trashed'])->orderBy('name')->pluck('name')->all();

        $this->assertSame(['Active', 'Deleted'], $names);
    }

    public function test_trashed_filter_is_ignored_when_not_authorized(): void
    {
        $deletedEntry = Entry::query()->create(['name' => 'Deleted']);
        $deletedEntry->delete();
        Entry::query()->create(['name' => 'Active']);
        $this->setFilters([['key' => 'trashed', 'operator' => '=', 'value' => true]]);

        $names = Entry::query()->filter(['name'])->pluck('name')->all();

        $this->assertSame(['Active'], $names);
    }
}

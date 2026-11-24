<?php

namespace Tests\Feature;

use EvanSchleret\LaravelApiResourceTool\ResourceTool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Tests\Models\Entry;
use Tests\TestCase;

class ResourceToolTest extends TestCase
{
    public function test_it_wraps_a_single_resource_without_legacy_flags(): void
    {
        $entry = Entry::query()->create(['name' => 'Example']);

        $response = ResourceTool::make($this->app['request'], new JsonResource($entry));
        $payload = $response->getData(true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame('', $payload['message']);
        $this->assertSame('Example', $payload['data']['name']);
        $this->assertArrayNotHasKey('ok', $payload);
    }

    public function test_it_returns_created_resources_with_a_201_status(): void
    {
        $entry = Entry::query()->create(['name' => 'Created']);

        $response = ResourceTool::created($this->app['request'], new JsonResource($entry), 'Created');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Created', $response->getData(true)['message']);
        $this->assertSame('Created', $response->getData(true)['data']['name']);
    }

    public function test_it_returns_accepted_responses_with_a_202_status(): void
    {
        $response = ResourceTool::accepted(['job_id' => 'job-123'], 'Queued');

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame([
            'status' => 'success',
            'message' => 'Queued',
            'data' => ['job_id' => 'job-123'],
        ], $response->getData(true));
    }

    public function test_it_returns_an_empty_204_response(): void
    {
        $response = ResourceTool::noContent();

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_it_resolves_an_eloquent_builder_for_a_collection(): void
    {
        Entry::query()->create(['name' => 'First']);
        Entry::query()->create(['name' => 'Second']);

        $response = ResourceTool::collection(
            $this->app['request'],
            new JsonResource(new Entry),
            Entry::query()->orderBy('name'),
        );
        $payload = $response->getData(true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(['First', 'Second'], array_column($payload['data'], 'name'));
    }

    public function test_it_flattens_custom_and_disabled_resource_wrappers(): void
    {
        Entry::query()->create(['name' => 'Example']);

        try {
            JsonResource::wrap('items');
            $wrappedResponse = ResourceTool::collection(
                $this->app['request'],
                new JsonResource(new Entry),
                Entry::query(),
            );

            $this->assertSame('Example', $wrappedResponse->getData(true)['data'][0]['name']);

            JsonResource::withoutWrapping();
            $unwrappedResponse = ResourceTool::collection(
                $this->app['request'],
                new JsonResource(new Entry),
                Entry::query(),
            );

            $this->assertSame('Example', $unwrappedResponse->getData(true)['data'][0]['name']);
            $this->assertArrayNotHasKey('0', $unwrappedResponse->getData(true));
        } finally {
            JsonResource::wrap('data');
        }
    }

    public function test_it_keeps_pagination_links_and_metadata_at_the_top_level(): void
    {
        Entry::query()->create(['name' => 'First']);
        Entry::query()->create(['name' => 'Second']);
        Entry::query()->create(['name' => 'Third']);
        config()->set('laravel-api-resource-tool.pagination.default_per_page', 2);
        $this->setQueryParameters(['filter' => 'active']);

        $response = ResourceTool::paginate(
            $this->app['request'],
            new JsonResource(new Entry),
            Entry::query()->orderBy('name'),
        );
        $payload = $response->getData(true);

        $this->assertCount(2, $payload['data']);
        $this->assertSame(3, $payload['meta']['total']);
        $this->assertNotNull($payload['links']['next']);
        $this->assertStringContainsString('filter=active', $payload['links']['next']);
    }

    public function test_it_uses_a_positive_per_page_query_parameter(): void
    {
        config()->set('laravel-api-resource-tool.pagination.default_per_page', 3);
        $this->setQueryParameters(['perPage' => '2']);

        $response = ResourceTool::paginate(
            $this->app['request'],
            new JsonResource(new Entry),
            Entry::query(),
        );

        $this->assertSame(2, $response->getData(true)['meta']['per_page']);
    }

    public function test_an_explicit_pagination_limit_takes_precedence_over_per_page(): void
    {
        $this->setQueryParameters(['perPage' => '1']);

        $response = ResourceTool::paginate(
            $this->app['request'],
            new JsonResource(new Entry),
            Entry::query(),
            2,
        );

        $this->assertSame(2, $response->getData(true)['meta']['per_page']);
    }

    public function test_it_uses_the_configured_default_for_invalid_per_page_values(): void
    {
        config()->set('laravel-api-resource-tool.pagination.default_per_page', 3);

        foreach (['invalid', '0', '-1', '2.5', ['2']] as $perPage) {
            $this->setQueryParameters(['perPage' => $perPage]);

            $response = ResourceTool::paginate(
                $this->app['request'],
                new JsonResource(new Entry),
                Entry::query(),
            );

            $this->assertSame(3, $response->getData(true)['meta']['per_page']);
        }
    }

    public function test_it_caps_page_number_and_cursor_pagination_limits(): void
    {
        config()->set('laravel-api-resource-tool.pagination.max_per_page', 3);
        $this->setQueryParameters(['perPage' => '6']);

        $pageResponse = ResourceTool::paginate(
            $this->app['request'],
            new JsonResource(new Entry),
            Entry::query(),
        );

        $this->assertSame(3, $pageResponse->getData(true)['meta']['per_page']);

        $explicitPageResponse = ResourceTool::paginate(
            $this->app['request'],
            new JsonResource(new Entry),
            Entry::query(),
            6,
        );

        $this->assertSame(3, $explicitPageResponse->getData(true)['meta']['per_page']);

        $cursorResponse = ResourceTool::cursorPaginate(
            $this->app['request'],
            new JsonResource(new Entry),
            Entry::query()->orderBy('id'),
            6,
        );

        $this->assertSame(3, $cursorResponse->getData(true)['meta']['per_page']);
    }

    public function test_it_returns_cursor_pagination_links_and_metadata(): void
    {
        Entry::query()->create(['name' => 'First']);
        Entry::query()->create(['name' => 'Second']);
        Entry::query()->create(['name' => 'Third']);
        config()->set('laravel-api-resource-tool.pagination.default_per_page', 1);
        $this->setQueryParameters(['filter' => 'active']);

        $response = ResourceTool::cursorPaginate(
            $this->app['request'],
            new JsonResource(new Entry),
            Entry::query()->orderBy('id'),
            2,
        );
        $payload = $response->getData(true);

        $this->assertCount(2, $payload['data']);
        $this->assertNull($payload['links']['prev']);
        $this->assertNotNull($payload['links']['next']);
        $this->assertStringContainsString('filter=active', $payload['links']['next']);
        $this->assertSame(2, $payload['meta']['per_page']);
        $this->assertArrayNotHasKey('total', $payload['meta']);
    }

    public function test_it_uses_the_same_success_envelope_for_content(): void
    {
        $response = ResourceTool::content(['id' => 42]);

        $this->assertSame([
            'status' => 'success',
            'message' => '',
            'data' => ['id' => 42],
        ], $response->getData(true));
    }

    public function test_it_uses_the_same_core_envelope_for_errors(): void
    {
        $response = ResourceTool::errorResponse('Not found', 404, 'NOT_FOUND');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame([
            'status' => 'error',
            'message' => 'Not found',
            'data' => null,
            'code' => 'NOT_FOUND',
        ], $response->getData(true));
    }

    public function test_it_adds_optional_field_errors_to_error_responses(): void
    {
        $response = ResourceTool::errorResponse(
            'The given data was invalid.',
            422,
            'VALIDATION_ERROR',
            ['email' => ['The email field is required.']],
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['The email field is required.'], $response->getData(true)['errors']['email']);
    }

    public function test_it_can_format_laravel_json_validation_errors_globally(): void
    {
        Route::get('/validation-errors', function (Request $request) {
            $request->validate(['email' => ['required', 'email']]);

            return ResourceTool::content();
        });

        $defaultResponse = $this->getJson('/validation-errors');
        $defaultResponse->assertUnprocessable()->assertJsonStructure(['message', 'errors']);

        config()->set('laravel-api-resource-tool.responses.validation_errors', true);

        $packageResponse = $this->getJson('/validation-errors');

        $packageResponse->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('data', null)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['email']]);
    }
}

<?php

namespace Tests;

use EvanSchleret\LaravelApiResourceTool\LaravelApiResourceToolServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelApiResourceToolServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->string('name');
            $table->string('status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('nickname')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id');
            $table->boolean('approved')->default(false);
            $table->timestamps();
        });
    }

    protected function setQueryParameters(array $parameters): void
    {
        $this->app['request']->query->replace($parameters);
    }

    protected function setFilters(array $filters): void
    {
        $this->setQueryParameters([
            'filter' => json_encode($filters, JSON_THROW_ON_ERROR),
        ]);
    }
}

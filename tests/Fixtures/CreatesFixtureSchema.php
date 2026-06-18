<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Fixtures;

use Illuminate\Support\Facades\Schema;

/**
 * Builds the handful of tables the test fixtures need, against the
 * in-memory SQLite connection configured in {@see \Nexus\CrudEngine\Tests\TestCase}.
 */
trait CreatesFixtureSchema
{
    protected function createFixtureSchema(): void
    {
        Schema::create('articles', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('cover_image')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function ($table) {
            $table->id();
            $table->foreignId('article_id');
            $table->string('body');
            $table->string('attachment')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('article_tag', function ($table) {
            $table->id();
            $table->foreignId('article_id');
            $table->foreignId('tag_id');
        });

        Schema::create('profiles', function ($table) {
            $table->id();
            $table->foreignId('article_id');
            $table->string('bio')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function ($table) {
            $table->id();
            $table->foreignId('profile_id');
            $table->string('theme')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function ($table) {
            $table->id();
            $table->string('title');
            $table->string('original_file')->nullable();
            $table->timestamps();
        });
    }
}

<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Unit\Relations;

use Nexus\CrudEngine\Contracts\Relations\RelationSyncManagerInterface;
use Nexus\CrudEngine\DTOs\Enums\RelationType;
use Nexus\CrudEngine\DTOs\RelationSyncContext;
use Nexus\CrudEngine\Services\Capabilities\CapabilityRegistry;
use Nexus\CrudEngine\Strategies\Relations\HasOneSyncStrategy;
use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\TestCase;

/**
 * Regression test for Bug 4.3 from the Phase 1 audit: the original
 * `HandleRelationHasOne` recursed into a nested relation by calling
 * `$existingRecord->getHasManyRelations()` — a copy-paste mistake. The
 * `Profile` fixture implements ONLY `HasOneRelations` (see its class
 * doc), so under the original buggy behavior this exact scenario would
 * throw a fatal "call to undefined method" error. Under the fix, it
 * must succeed and actually create the nested `Setting` row.
 */
final class HasOneSyncStrategyTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureSchema();
    }

    public function test_syncing_a_has_one_relation_recurses_correctly_into_a_model_that_only_implements_has_one_relations(): void
    {
        $article = Article::create(['title' => 'Hello world']);

        $strategy = $this->app->make(HasOneSyncStrategy::class);

        // Article -> profile (hasOne) -> settings (hasOne, nested).
        // Profile implements HasOneRelations only — no getHasManyRelations()
        // method exists on it at all.
        $strategy->sync(new RelationSyncContext(
            model: $article,
            relationName: 'profile',
            incomingData: [
                'bio' => 'Backend engineer',
                'settings' => ['theme' => 'dark'],
            ],
            type: RelationType::HasOne,
        ));

        $profile = $article->profile()->first();

        $this->assertNotNull($profile, 'Expected the profile to have been created.');
        $this->assertSame('Backend engineer', $profile->bio);

        $setting = $profile->settings()->first();

        $this->assertNotNull(
            $setting,
            'Expected the nested "settings" relation to have been synced via getHasOneRelations(), '.
            'not thrown a fatal error trying to call getHasManyRelations() (Bug 4.3).'
        );
        $this->assertSame('dark', $setting->theme);
    }

    public function test_updating_an_existing_has_one_relation_does_not_create_a_duplicate(): void
    {
        $article = Article::create(['title' => 'Hello world']);
        $article->profile()->create(['bio' => 'Old bio']);

        $strategy = $this->app->make(HasOneSyncStrategy::class);

        $strategy->sync(new RelationSyncContext(
            model: $article,
            relationName: 'profile',
            incomingData: ['bio' => 'New bio'],
            type: RelationType::HasOne,
        ));

        $this->assertSame(1, $article->profile()->count());
        $this->assertSame('New bio', $article->profile()->first()->bio);
    }

    public function test_capability_registry_confirms_profile_does_not_support_has_many(): void
    {
        $registry = new CapabilityRegistry();
        $profile = new \Nexus\CrudEngine\Tests\Fixtures\Models\Profile();

        $this->assertFalse(
            $registry->supportsHasMany($profile),
            'Profile must NOT report HasMany support — this is the exact condition under which the original bug occurred.'
        );
        $this->assertTrue($registry->supportsHasOne($profile));
    }
}

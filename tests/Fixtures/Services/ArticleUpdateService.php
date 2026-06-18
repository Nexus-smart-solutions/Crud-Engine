<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Fixtures\Services;

use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Services\Crud\AbstractUpdateService;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\Fixtures\Requests\ArticleRequest;

/**
 * The target model is provided via {@see forArticle()} after the service
 * is resolved from the container, rather than through the constructor —
 * this keeps the constructor container-resolvable purely by type-hint
 * (matching how a controller action would resolve it after a route-model
 * binding), instead of fighting the container with a mixed
 * typed/variadic signature.
 */
final class ArticleUpdateService extends AbstractUpdateService
{
    private ?Article $article = null;

    public function forArticle(Article $article): static
    {
        $this->article = $article;

        return $this;
    }

    public function model(): string
    {
        return Article::class;
    }

    public function requestFile(): string
    {
        return ArticleRequest::class;
    }

    public function resolveModel(): Model
    {
        if ($this->article === null) {
            throw new \LogicException('Call forArticle() before update().');
        }

        return $this->article;
    }
}

<?php
declare(strict_types=1);

namespace Tests\LoyaltyCorp\Search\Stubs\Handlers;

use LoyaltyCorp\Search\Interfaces\TransformableSearchHandlerInterface;
use Tests\LoyaltyCorp\Search\Stubs\Entities\EntityStub;

/**
 * @coversNothing
 */
final class InvalidMappingHandlerStub implements TransformableSearchHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getHandlerKey(): string
    {
        return '';
    }
    /**
     * {@inheritdoc}
     */
    public static function getMappings(): array
    {
        return ['doc' => [], 'not-doc' => []];
    }

    /**
     * {@inheritdoc}
     */
    public static function getSettings(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getFillIterable(): iterable
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexName(): string
    {
        return 'entity_stub';
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchId(object $object)
    {
        return \method_exists($object, 'getSearchId') ? $object->getSearchId() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($object = null): ?array
    {
        return [];
    }
}

<?php
declare(strict_types=1);

namespace LoyaltyCorp\Search\Transformers;

use LoyaltyCorp\Search\Interfaces\SearchHandlerInterface;
use LoyaltyCorp\Search\Interfaces\Transformers\IndexTransformerInterface;

final class DefaultIndexTransformer implements IndexTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transformIndexName(SearchHandlerInterface $handler, object $object): string
    {
        return \mb_strtolower($handler->getIndexName());
    }

    /**
     * {@inheritdoc}
     */
    public function transformIndexNames(SearchHandlerInterface $searchHandler): array
    {
        return [
            \mb_strtolower($searchHandler->getIndexName())
        ];
    }
}

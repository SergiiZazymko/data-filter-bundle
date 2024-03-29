<?php
/**
 * @author Sergey Hashimov
 */

namespace Slmder\SlmderFilterBundle\Filtration\QueryHandlerStrategy;

use Doctrine\ORM\QueryBuilder;

/**
 * Interface HandlerStrategyInterface
 * @package App\Filtration\QueryHandlerStrategy
 */
interface HandlerStrategyInterface
{
    /**
     * @param QueryBuilder $queryBuilder
     * @param array $query
     * @return mixed
     */
    function handle(QueryBuilder $queryBuilder, array $query);

    /**
     * @return string
     */
    function getProcessingKeyName(): string;
}
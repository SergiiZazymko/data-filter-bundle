<?php
/**
 * @author Sergey Hashimov
 */

namespace Slmder\SlmderFilterBundle\Filtration\QueryHandlerStrategy\Impl;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Slmder\SlmderFilterBundle\Filtration\Common\{EntityInfo,
    Expression,
    ExpressionBuilder,
    JoinMaker,
    Model\PropertyPath};
use Slmder\SlmderFilterBundle\Filtration\Common\PropertyPathProvider\PropertyPathProviderInterface;
use Slmder\SlmderFilterBundle\Filtration\QueryHandlerStrategy\HandlerStrategyInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class CrossFieldFilterQueryHandlerStrategy
 * @package App\Filtration\QueryHandlerStrategy
 */
class DisjunctionQueryHandlerStrategy implements HandlerStrategyInterface
{
    /**
     * @var string
     */
    const PROCESSING_KEY = 'cfFilter';

    /**
     * @var EntityInfo
     */
    private $entityInfo;
    /**
     * @var JoinMaker
     */
    private $joinMaker;
    /**
     * @var ExpressionBuilder
     */
    private $builder;
    /**
     * @var PropertyPathProviderInterface
     */
    private $provider;
    /**
     * @var string
     */
    private $defaultOperator;

    public function __construct(
        EntityInfo $entityInfo,
        JoinMaker $joinMaker,
        ExpressionBuilder $builder,
        PropertyPathProviderInterface $provider,
        string $defaultOperator
    ) {
        $this->entityInfo = $entityInfo;
        $this->joinMaker = $joinMaker;
        $this->builder = $builder;
        $this->provider = $provider;
        $this->defaultOperator = $defaultOperator;
    }

    /**
     * @return string
     */
    function getProcessingKeyName(): string
    {
        return self::PROCESSING_KEY;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array $query
     * @return mixed
     */
    function handle(QueryBuilder $queryBuilder, array $query)
    {
        $rootAlias = $this->entityInfo->rootAlias($queryBuilder);
        foreach ($query as $i => $item) {
            if (!isset($item)) {
                throw new BadRequestHttpException("Fields not defined.");
            }
            $paths = $this->provider->createPaths($item);
            $expressions = [];
            /** @var PropertyPath $path */
            foreach ($paths as $path) {
                $aliasedPath = $this->joinMaker->make($queryBuilder, $path->getPath(), $rootAlias);
                $operator = $this->defaultOperator;
                if (!$path->emptyOperator()) {
                    $operator = $path->getOperator();
                }
                if (!in_array(strtolower($operator), ExpressionBuilder::ALL)) {
                    throw new BadRequestHttpException("Unknown operator '{$operator}'.");
                }
                $expressions[] = $this->builder->$operator($aliasedPath, $path->getValue());
            }
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(
                    ...array_map(
                        function (Expression $e) {
                            return $e->getExpr();
                        },
                        $expressions
                    )
                )
            );
            foreach ($expressions as $expression) {
                if ($expression->getParameter() && $expression->getParameter() instanceof ArrayCollection) {
                    foreach ($expression->getParameter() as $param) {
                        $queryBuilder->getParameters()->add($param);
                    }
                    continue;
                }
                if ($expression->getParameter()) {
                    $queryBuilder->getParameters()->add($expression->getParameter());
                }
            }
        }
    }

}
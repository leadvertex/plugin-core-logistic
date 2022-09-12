<?php

namespace Leadvertex\Helpers;

use Leadvertex\Plugin\Core\Logistic\Services\LogisticStatusesResolverService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

abstract class LogisticTestCase extends TestCase
{
    /**
     * @param $name
     * @return ReflectionMethod
     * @throws ReflectionException
     */
    public static function getMethod($name): ReflectionMethod
    {
        $class = new ReflectionClass(LogisticStatusesResolverService::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
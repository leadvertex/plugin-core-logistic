<?php

namespace Leadvertex\Plugin\Core\Logistic\Components\Actions;

use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;

interface ActionInterface
{
    public function __invoke(Request $request, Response $response, array $args): Response;
}
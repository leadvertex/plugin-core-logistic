<?php

namespace Leadvertex\Plugin\Core\Logistic\Components\Actions\Track;

use Leadvertex\Plugin\Components\Db\Exceptions\DatabaseException;
use Leadvertex\Plugin\Core\Actions\ActionInterface;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Track;
use Leadvertex\Plugin\Core\Logistic\Services\LogisticStatusesResolverService;
use ReflectionException;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;

class TrackGetStatusesAction implements ActionInterface
{

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws DatabaseException
     * @throws ReflectionException
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $trackNumber = $args['trackNumber'];

        $tracks = Track::findByTrack($trackNumber);
        if (empty($tracks)) {
            return $response->withJson([
                'errors' => sprintf('Track with number %s is not found', $trackNumber),
            ], 404);
        }

        $track = reset($tracks);
        $service = new LogisticStatusesResolverService($track);
        $statuses = $service->sort();

        return $response->withJson([
            'statuses' => $statuses,
        ]);
    }
}
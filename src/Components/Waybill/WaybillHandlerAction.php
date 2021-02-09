<?php
/**
 * Created for plugin-core-logistic
 * Date: 09.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Logistic\Components\Waybill;


use Leadvertex\Plugin\Components\Access\Registration\Registration;
use Leadvertex\Plugin\Components\Form\FormData;
use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Core\Actions\ActionInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class WaybillHandlerAction implements ActionInterface
{

    /**
     * @param ServerRequest $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws Exception\WaybillContainerException
     */
    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $form = WaybillContainer::getForm();
        $data = new FormData($request->getParsedBody());

        $errors = $form->getErrors($data);
        if (!empty($errors)) {
            return $response->withJson($errors, 400);
        }

        $waybillResponse = WaybillContainer::getHandler()($form, $data);

        /** @var Registration $registration */
        $registration = Registration::find();

        $logistic = $registration->getOutputToken([
            'waybill' => $waybillResponse->logistic->getWaybill(),
            'status' => $waybillResponse->logistic->getStatus(),
            'data' => $waybillResponse->logistic->getData() ?? $data->all()
        ], 60 * 60 * 6);

        return $response->withJson([
            'logistic' => (string) $logistic,
            'address' => $waybillResponse->address,
            'waybill' => $waybillResponse->logistic->getWaybill(),
            'status' => [
                'timestamp' => $waybillResponse->logistic->getStatus()->getTimestamp(),
                'code' => LogisticStatus::code2strings()[$waybillResponse->logistic->getStatus()->getCode()],
                'text' => $waybillResponse->logistic->getStatus()->getText(),
            ],
        ]);
    }
}
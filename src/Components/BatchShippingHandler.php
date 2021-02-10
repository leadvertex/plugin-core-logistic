<?php
/**
 * Created for plugin-core-logistic
 * Date: 10.02.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Logistic\Components;


use Adbar\Dot;
use GuzzleHttp\Psr7\Response;
use Leadvertex\Plugin\Components\Access\Registration\Registration;
use Leadvertex\Plugin\Components\Batch\Batch;
use Leadvertex\Plugin\Components\Batch\BatchHandlerInterface;
use Leadvertex\Plugin\Components\Guzzle\Guzzle;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use XAKEPEHOK\Path\Path;

abstract class BatchShippingHandler implements BatchHandlerInterface
{

    protected function createShipping(Batch $batch): int
    {
        $token = $batch->getToken()->getInputToken();
        $uri = (new Path($token->getClaim('iss')))
            ->down('companies')
            ->down($token->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping');

        $response = Guzzle::getInstance()->post(
            (string) $uri,
            [
                'headers' => [
                    'X-PLUGIN-TOKEN' => (string) $batch->getToken()->getOutputToken(),
                ],
                'json' => [],
            ],
        );

        if ($response->getStatusCode() !== 201) {
            throw new RuntimeException('Invalid response code', 100);
        }

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['shippingId'])) {
            throw new RuntimeException('Invalid response', 200);
        }

        return $data['shippingId'];
    }

    protected function lockOrder(int $timeout, Dot $order, Batch $batch): bool
    {
        $client = $batch->getApiClient();

        $query = '
            mutation($id: ID!, $timeout: Int!) {
              orderMutation {
                lockOrder(input: {id: $id, timeout: $timeout})
              }
            }
        ';

        $response = new Dot($client->query($query, [
            'id' => $order['id'],
            'timeout' => $timeout,
        ])->getData());

        return $response->get('orderMutation.lockOrder', false);
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @param array $orders
     * @return Response|ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function addOrders(Batch $batch, string $shippingId, array $orders): ResponseInterface
    {
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping')
            ->down($shippingId)
            ->down('orders');

        return Registration::find()->makeSpecialRequest(
            'PATCH',
            $uri,
            [
                'shippingId' => $shippingId,
                'orders' => $orders,
            ],
            60 * 10
        );
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @param int $ordersCount
     * @return Response|ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function markAsCompleted(Batch $batch, string $shippingId, int $ordersCount): ResponseInterface
    {
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping')
            ->down($shippingId);

        return Registration::find()->makeSpecialRequest(
            'POST',
            $uri,
            [
                'shippingId' => $shippingId,
                'status' => "completed",
                'orders' => $ordersCount,
            ],
            60 * 10
        );
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @return Response|ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function markAsFailed(Batch $batch, string $shippingId): ResponseInterface
    {
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping')
            ->down($shippingId);

        return Registration::find()->makeSpecialRequest(
            'POST',
            $uri,
            [
                'shippingId' => $shippingId,
                'status' => "failed",
            ],
            60 * 10
        );
    }

}
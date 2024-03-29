<?php
/**
 * Created for plugin-core-logistic
 * Date: 10.02.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Logistic\Components;


use Adbar\Dot;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Leadvertex\Plugin\Components\Access\Registration\Registration;
use Leadvertex\Plugin\Components\Batch\Batch;
use Leadvertex\Plugin\Components\Batch\BatchHandlerInterface;
use Leadvertex\Plugin\Components\Db\Helpers\UuidHelper;
use Leadvertex\Plugin\Components\Guzzle\Guzzle;
use Leadvertex\Plugin\Components\Logistic\Components\ShippingAttachment;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use XAKEPEHOK\Path\Path;

abstract class BatchShippingHandler implements BatchHandlerInterface
{

    protected string $lockId;

    public function __construct()
    {
        $this->lockId = UuidHelper::getUuid();
    }

    protected function createShipping(Batch $batch, $removeOnCancelFields = []): int
    {
        $batch->getApiClient()::$lockId = $this->lockId;

        $token = $batch->getToken()->getInputToken();
        $uri = (new Path($token->getClaim('iss')))
            ->down('companies')
            ->down($token->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping');

        $response = Guzzle::getInstance()->post(
            (string)$uri,
            [
                'headers' => [
                    'X-PLUGIN-TOKEN' => (string)$batch->getToken()->getOutputToken(),
                ],
                'json' => [
                    'lockId' => $this->lockId,
                    'removeOnCancelFields' => $removeOnCancelFields,
                ],
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

    protected function lockOrder(int $timeout, int $orderId, Batch $batch): bool
    {
        $batch->getApiClient()::$lockId = $this->lockId;
        $client = $batch->getApiClient();

        $query = '
            mutation($id: ID!, $timeout: Int!) {
              lockMutation {
                lockEntity(input: { entity: { entity: Order, id: $id }, timeout: $timeout })
              }
            }
        ';

        $response = new Dot($client->query($query, [
            'id' => $orderId,
            'timeout' => $timeout,
        ])->getData());

        return $response->get('lockMutation.lockEntity', false);
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @param array $orders
     * @return Response|ResponseInterface
     * @throws GuzzleException
     */
    protected function addOrders(Batch $batch, string $shippingId, array $orders): ResponseInterface
    {
        $batch->getApiClient()::$lockId = $this->lockId;
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
                'lockId' => $this->lockId,
            ],
            60 * 10
        );
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @param int $ordersCount
     * @return Response|ResponseInterface
     * @throws GuzzleException
     */
    protected function markAsExported(Batch $batch, string $shippingId, int $ordersCount): ResponseInterface
    {
        $batch->getApiClient()::$lockId = $this->lockId;
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping')
            ->down($shippingId)
            ->down('status/exported');

        return Registration::find()->makeSpecialRequest(
            'POST',
            $uri,
            [
                'shippingId' => $shippingId,
                'orders' => $ordersCount,
                'lockId' => $this->lockId,
            ],
            60 * 10
        );
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @return Response|ResponseInterface
     * @throws GuzzleException
     */
    protected function markAsFailed(Batch $batch, string $shippingId): ResponseInterface
    {
        $batch->getApiClient()::$lockId = $this->lockId;
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping')
            ->down($shippingId)
            ->down('status/failed');

        return Registration::find()->makeSpecialRequest(
            'POST',
            $uri,
            [
                'shippingId' => $shippingId,
                'lockId' => $this->lockId,
            ],
            60 * 10
        );
    }

    /**
     * @param Batch $batch
     * @param string $shippingId
     * @param ShippingAttachment ...$shippingAttachment
     * @return ResponseInterface
     * @throws GuzzleException
     */
    protected function addShippingAttachments(Batch $batch, string $shippingId, ShippingAttachment ...$shippingAttachment): ResponseInterface
    {
        $batch->getApiClient()::$lockId = $this->lockId;
        $inputToken = $batch->getToken()->getInputToken();
        $uri = (new Path($inputToken->getClaim('iss')))
            ->down('companies')
            ->down($inputToken->getClaim('cid'))
            ->down('CRM/plugin/logistic/shipping')
            ->down($shippingId)
            ->down('attachments/add');

        return Registration::find()->makeSpecialRequest(
            'PATCH',
            $uri,
            [
                'shippingId' => $shippingId,
                'lockId' => $this->lockId,
                'attachments' => $shippingAttachment,
            ],
            60 * 10
        );
    }

}
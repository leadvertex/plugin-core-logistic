<?php

namespace Leadvertex\Plugin\Core\Logistic\Components\Track;

use Exception;
use Leadvertex\Plugin\Components\Access\Registration\Registration;
use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Db\Exceptions\DatabaseException;
use Leadvertex\Plugin\Components\Db\Model;
use Leadvertex\Plugin\Components\Logistic\Exceptions\LogisticOfficePhoneException;
use Leadvertex\Plugin\Components\Logistic\Exceptions\LogisticStatusTooLongException;
use Leadvertex\Plugin\Components\Logistic\LogisticOffice;
use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Components\Logistic\Waybill\Waybill;
use Leadvertex\Plugin\Components\SpecialRequestDispatcher\Components\SpecialRequest;
use Leadvertex\Plugin\Components\SpecialRequestDispatcher\Models\SpecialRequestTask;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Exception\TrackException;
use Leadvertex\Plugin\Core\Logistic\Services\LogisticStatusesResolverService;
use Medoo\Medoo;
use XAKEPEHOK\EnumHelper\Exception\OutOfEnumException;
use XAKEPEHOK\Path\Path;

class Track extends Model
{
    protected string $companyId;

    protected string $pluginAlias;

    protected string $pluginId;


    protected string $track;

    protected string $shippingId;

    protected int $createdAt;

    protected ?int $nextTrackingAt = null;

    protected ?int $lastTrackedAt = null;

    protected array $statuses = [];

    protected array $notificationsHashes = [];

    protected ?int $notifiedAt = null;

    protected ?int $stoppedAt = null;

    protected string $segment;

    protected Waybill $waybill;

    protected ?LogisticOffice $logisticOffice = null;

    public function __construct(PluginReference $pluginReference, Waybill $waybill, string $shippingId, string $orderId)
    {
        $this->companyId = $pluginReference->getCompanyId();
        $this->pluginAlias = $pluginReference->getAlias();
        $this->pluginId = $pluginReference->getId();

        $this->id = $orderId;
        $this->waybill = $waybill;
        $this->track = $waybill->getTrack();
        $this->shippingId = $shippingId;
        $this->createdAt = time();
        $this->segment = mb_substr(md5($waybill->getTrack()), -1);
    }

    public function getCompanyId(): string
    {
        return $this->companyId;
    }

    public function getPluginAlias(): string
    {
        return $this->pluginAlias;
    }

    public function getPluginId(): string
    {
        return $this->pluginId;
    }

    public function getTrack(): string
    {
        return $this->track;
    }

    public function getShippingId(): string
    {
        return $this->shippingId;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getLastTrackedAt(): ?int
    {
        return $this->lastTrackedAt;
    }

    public function setLastTrackedAt(): void
    {
        $this->lastTrackedAt = time();
    }

    public function getNextTrackingAt(): ?int
    {
        return $this->nextTrackingAt;
    }

    public function setNextTrackingAt(int $minutes): void
    {
        $this->nextTrackingAt = time() + $minutes * 60;
    }

    /**
     * @return LogisticStatus[]
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }

    /**
     * @param LogisticStatus $status
     * @return void
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @throws TrackException
     */
    public function addStatus(LogisticStatus $status): void
    {
        $filtered = self::mergeStatuses($this->statuses, [$status]);
        $status = end($filtered);
        if ($status !== false) {
            $this->statuses[] = $status;
            $this->createNotification();
        }
    }

    /**
     * @param LogisticStatus[] $statuses
     * @return void
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @throws TrackException
     */
    public function setStatuses(array $statuses): void
    {
        $oldStatusesCount = count($this->statuses);
        $this->statuses = self::mergeStatuses($this->statuses, $statuses);
        if ($oldStatusesCount != count($this->statuses)) {
            $this->createNotification();
        }
    }

    /**
     * Фильтрует новые статусы, которые пришли от конкретной реализации плагина логистики.
     * Находим hash от каждого нового статуса и сверяем их с hash уже существующих и оставляем только те, которые не совпали.
     *
     * @param array $current
     * @param array $new
     * @return array
     */
    public static function filterNewStatutes(array $current, array $new): array
    {
        $hashes = array_map(fn(LogisticStatus $status) => $status->getHash(), $current);
        return array_filter($new, fn(LogisticStatus $status) => !in_array($status->getHash(), $hashes));
    }

    /**
     * Данный метод больше необходим для простановки маппинга кода @see LogisticStatus::RETURNING_TO_SENDER.
     *
     * @param LogisticStatus[] $current
     * @param LogisticStatus[] $new
     * @return array
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     */
    public static function mergeStatuses(array $current, array $new): array
    {
        $new = self::filterNewStatutes($current, $new);
        $current = array_merge($current, $new);

        /**
         * Проверяем есть ли в статусах @see LogisticStatus::RETURNED
         * если его нет, то просто отдаем отфильтрованные статусы
         */
        $returnStatuses = array_filter($current, function (LogisticStatus $status) {
            return $status->getCode() === LogisticStatus::RETURNED;
        });
        if (empty($returnStatuses)) {
            return $current;
        }

        //сортируем статусы по времени
        usort($current, function (LogisticStatus $status_1, LogisticStatus $status_2) {
            if ($status_1->getTimestamp() === $status_2->getTimestamp()) {
                return 0;
            }
            return ($status_1->getTimestamp() < $status_2->getTimestamp()) ? -1 : 1;
        });

        /**
         * Находим статус возврата и все последующие после него искусственно делаем со статусом @see LogisticStatus::RETURNING_TO_SENDER
         * исключение есть только для статуса @see LogisticStatus::DELIVERED_TO_SENDER
         */
        $afterReturned = false;
        foreach ($current as &$status) {
            if ($afterReturned && $status->getCode() !== LogisticStatus::DELIVERED_TO_SENDER) {
                $status = new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, $status->getText(), $status->getTimestamp());
            }
            if ($status->getCode() === LogisticStatus::RETURNED) {
                $afterReturned = true;
            }
        }

        return $current;
    }

    public function getNotificationsHashes(): array
    {
        return $this->notificationsHashes;
    }

    public function setNotified(LogisticStatus $status): void
    {
        $this->notificationsHashes[] = $status->getHash();
        $this->notifiedAt = time();
    }

    public function getNotifiedAt(): ?int
    {
        return $this->notifiedAt;
    }

    protected function createNotification(): void
    {
        $service = new LogisticStatusesResolverService($this);
        $lastStatus = $service->getLastStatusForNotify();
        if ($lastStatus === null) {
            return;
        }

        Connector::setReference(new PluginReference($this->getCompanyId(), $this->getPluginAlias(), $this->getPluginId()));
        $registration = Registration::find();
        if ($registration === null) {
            throw new TrackException('Failed to create notify. Plugin is not registered.');
        }
        $uri = (new Path($registration->getClusterUri()))
            ->down('companies')
            ->down(Connector::getReference()->getCompanyId())
            ->down('CRM/plugin/logistic/shipping/status');

        $this->setNotified($lastStatus);

        $jwt = $registration->getSpecialRequestToken([
            'orderId' => $this->getId(),
            'waybill' => $this->getWaybill()->jsonSerialize(),
            'statuses' => $service->sort(),
            'status' => $lastStatus->jsonSerialize(),
            'info' => $this->getLogisticOffice() !== null ? $this->getLogisticOffice()->jsonSerialize() : null,
            'data' => null,
        ], 24 * 60 * 60);

        $body = json_encode([
            'request' => $jwt,
        ]);

        $request = new SpecialRequest(
            'PATCH',
            $uri,
            $body,
            24 * 60 * 60,
            202,
        );
        $task = new SpecialRequestTask($request);
        $task->save();
    }

    public function getStoppedAt(): ?int
    {
        return $this->stoppedAt;
    }

    public function setStoppedAt(): void
    {
        $this->stoppedAt = time();
    }

    public function getWaybill(): Waybill
    {
        return $this->waybill;
    }

    public function setWaybill(Waybill $waybill): void
    {
        $this->waybill = $waybill;
    }

    public function getLogisticOffice(): ?LogisticOffice
    {
        return $this->logisticOffice;
    }

    public function setLogisticOffice(LogisticOffice $logisticOffice): void
    {
        $this->logisticOffice = $logisticOffice;
    }

    /**
     * @param string $segments allowed md5 chars separated by comma
     * @param int $limit
     * @return array
     * @throws Exception
     */
    public static function findForTracking(string $segments = '', int $limit = 3000): array
    {
        if (!empty($segments)) {
            $segments = explode(',', $segments);
        }

        $where = [
            'AND' => [
                'createdAt[>=]' => time() - 24 * 60 * 60 * 30 * 5,
                'stoppedAt' => null,
                'OR #nextTrackingAt' => [
                    'nextTrackingAt' => null,
                    'nextTrackingAt[<=]' => time(),
                ],
                'OR #lastTrackedAt' => [
                    'lastTrackedAt' => null,
                    'lastTrackedAt[<=]' => time() - 60 * 60,
                ],
            ],
        ];

        if (!empty($segments)) {
            $where['AND'][] = ['segment' => $segments];
        }
        $where['LIMIT'] = $limit;
        $where['ORDER'] = 'lastTrackedAt';

        return self::findByCondition($where);
    }

    protected static function beforeWrite(array $data): array
    {
        $data = parent::beforeWrite($data);

        $data['statuses'] = json_encode($data['statuses']);
        $data['notificationsHashes'] = json_encode($data['notificationsHashes']);
        $data['waybill'] = json_encode($data['waybill']);
        $data['logisticOffice'] = json_encode($data['logisticOffice']);
        return $data;
    }

    /**
     * @param array $data
     * @return array
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @throws LogisticOfficePhoneException
     */
    protected static function afterRead(array $data): array
    {
        $data = parent::afterRead($data);

        $data['statuses'] = array_map(function (array $item) {
            return new LogisticStatus($item['code'], $item['text'], $item['timestamp']);
        }, json_decode($data['statuses'], true));
        $data['notificationsHashes'] = json_decode($data['notificationsHashes'], true);
        $data['waybill'] = Waybill::createFromArray(json_decode($data['waybill'], true));
        $logisticOfficeData = json_decode($data['logisticOffice'], true);
        $data['logisticOffice'] = $logisticOfficeData !== null
            ? LogisticOffice::createFromArray(json_decode($data['logisticOffice'], true))
            : null;
        return $data;
    }

    public static function tableName(): string
    {
        return 'tracks';
    }

    public static function schema(): array
    {
        return [
            'companyId' => ['INT', 'NOT NULL'],
            'pluginAlias' => ['VARCHAR(255)', 'NOT NULL'],
            'pluginId' => ['INT', 'NOT NULL'],
            'track' => ['VARCHAR(50)'],
            'shippingId' => ['VARCHAR(50)'],
            'createdAt' => ['INT', 'NOT NULL'],
            'nextTrackingAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'lastTrackedAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'statuses' => ['TEXT'],
            'notificationsHashes' => ['TEXT'],
            'notifiedAt' => ['INT'],
            'stoppedAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'waybill' => ['TEXT'],
            'logisticOffice' => ['TEXT'],
            'segment' => ['CHAR(1)'],
        ];
    }

    public static function afterTableCreate(Medoo $db): void
    {
        $db->exec('CREATE INDEX `toUpdate` on ' . self::tableName() . ' (`createdAt`, `nextTrackingAt`, `stoppedAt`, `segment`)');
        $db->exec('CREATE INDEX `lastTrackedAt` on ' . self::tableName() . ' (`lastTrackedAt`)');
        DatabaseException::guard($db);
    }

}
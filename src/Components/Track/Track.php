<?php

namespace Leadvertex\Plugin\Core\Logistic\Components\Track;

use Exception;
use Leadvertex\Plugin\Components\Access\Registration\Registration;
use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Db\Exceptions\DatabaseException;
use Leadvertex\Plugin\Components\Db\Helpers\UuidHelper;
use Leadvertex\Plugin\Components\Db\Model;
use Leadvertex\Plugin\Components\Logistic\Exceptions\LogisticStatusTooLongException;
use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Components\SpecialRequestDispatcher\Components\SpecialRequest;
use Leadvertex\Plugin\Components\SpecialRequestDispatcher\Models\SpecialRequestTask;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Exception\TrackException;
use Leadvertex\Plugin\Core\Logistic\Services\LogisticStatusesResolverService;
use Medoo\Medoo;
use ReflectionException;
use XAKEPEHOK\EnumHelper\Exception\OutOfEnumException;
use XAKEPEHOK\Path\Path;

class Track extends Model
{
    protected string $companyId;

    protected string $pluginAlias;

    protected string $pluginId;


    protected string $track;

    protected string $shippingId;

    protected string $orderId;

    protected int $createdAt;

    protected ?int $nextTrackingAt = null;

    protected ?int $lastTrackedAt = null;

    protected array $statuses = [];

    protected array $notificationsHashes = [];

    protected ?int $notifiedAt = null;

    protected ?int $stoppedAt = null;

    protected bool $isCod;

    protected string $segment;

    public function __construct(PluginReference $pluginReference, string $track, string $shippingId, string $orderId, bool $isCod)
    {
        $this->companyId = $pluginReference->getCompanyId();
        $this->pluginAlias = $pluginReference->getAlias();
        $this->pluginId = $pluginReference->getId();

        $this->id = UuidHelper::getUuid();
        $this->track = $track;
        $this->shippingId = $shippingId;
        $this->orderId = $orderId;
        $this->createdAt = time();
        $this->isCod = $isCod;
        $this->segment = mb_substr(md5($track), -1);
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

    public function getOrderId(): string
    {
        return $this->orderId;
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
     * @throws TrackException
     */
    public function addStatus(LogisticStatus $status): void
    {
        $filtered = self::filterNewStatutes($this->statuses, [$status]);
        $status = reset($filtered);
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

    public static function filterNewStatutes(array $current, array $new): array
    {
        $hashes = array_map(fn(LogisticStatus $status) => $status->getHash(), $current);
        return array_filter($new, fn(LogisticStatus $status) => !in_array($status->getHash(), $hashes));
    }

    /**
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

        $returnStatuses = array_filter($current, function (LogisticStatus $status) {
            return $status->getCode() === LogisticStatus::RETURNED;
        });
        if (empty($returnStatuses)) {
            return $current;
        }

        usort($current, function (LogisticStatus $status_1, LogisticStatus $status_2) {
            if ($status_1->getTimestamp() === $status_2->getTimestamp()) {
                return 0;
            }
            return ($status_1->getTimestamp() < $status_2->getTimestamp()) ? -1 : 1;
        });

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
            ->down('CRM/plugin/logistic/shipping/orders');

        $this->setNotified($lastStatus);
        $codes = array_map(fn(LogisticStatus $status) => $status->getCode(), $this->getStatuses());

        $jwt = $registration->getSpecialRequestToken([
            'shippingId' => $this->getShippingId(),
            'orderId' => $this->getOrderId(),
            'status' => $lastStatus->jsonSerialize(),
            'codes' => $codes,
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

    public function isCod(): bool
    {
        return $this->isCod;
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
        $data['isCod'] = (int)$data['isCod'];
        return $data;
    }

    /**
     * @param array $data
     * @return array
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     */
    protected static function afterRead(array $data): array
    {
        $data = parent::afterRead($data);

        $data['statuses'] = array_map(function (array $item) {
            return new LogisticStatus($item['code'], $item['text'], $item['timestamp']);
        }, json_decode($data['statuses'], true));
        $data['notifications'] = json_decode($data['notifications'], true);
        $data['isCod'] = (bool)$data['isCod'];
        return $data;
    }

    public static function tableName(): string
    {
        return 'tracks';
    }

    public static function schema(): array
    {
        return array_merge([
            'orderId' => ['VARCHAR(50)'],
            'track' => ['VARCHAR(50)'],
            'shippingId' => ['VARCHAR(50)'],
            'createdAt' => ['INT', 'NOT NULL'],
            'nextTrackingAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'lastTrackedAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'statuses' => ['TEXT'],
            'notifications' => ['TEXT'],
            'notifiedAt' => ['INT'],
            'stoppedAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'isCod' => ['INT'],
            'segment' => ['CHAR(1)'],
        ]);
    }

    public static function afterTableCreate(Medoo $db): void
    {
        $db->exec('CREATE INDEX `toUpdate` on ' . self::tableName() . ' (`createdAt`, `nextTrackingAt`, `stoppedAt`, `segment`)');
        $db->exec('CREATE INDEX `lastTrackedAt` on ' . self::tableName() . ' (`lastTrackedAt`)');
        DatabaseException::guard($db);
    }

}
<?php

namespace Leadvertex\Plugin\Core\Logistic\Components\Track;

use Exception;
use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Db\Exceptions\DatabaseException;
use Leadvertex\Plugin\Components\Db\Helpers\UuidHelper;
use Leadvertex\Plugin\Components\Logistic\Exceptions\LogisticStatusTooLongException;
use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Components\SpecialRequestDispatcher\Models\SpecialRequestTask;
use Medoo\Medoo;
use ReflectionException;
use XAKEPEHOK\EnumHelper\Exception\OutOfEnumException;

class Track extends SpecialRequestTask
{

    public string $track;

    public string $shippingId;

    public int $createdAt;

    public ?int $nextTrackingAt = null;

    public ?int $lastTrackedAt = null;

    public array $statuses = [];

    public array $notificationsHashes = [];

    public ?int $notifiedAt = null;

    public ?int $stoppedAt = null;

    public bool $isCod;

    public string $segment;


    public function __construct(PluginReference $pluginReference, string $track, string $shippingId, bool $isCod)
    {
        $this->companyId = $pluginReference->getCompanyId();
        $this->pluginAlias = $pluginReference->getAlias();
        $this->pluginId = $pluginReference->getId();

        $this->id = UuidHelper::getUuid();
        $this->track = $track;
        $this->shippingId = $shippingId;
        $this->createdAt = time();
        $this->isCod = $isCod;
        $this->segment = mb_substr(md5($track), -1);
    }

    public function getLastTrackedAt(): ?int
    {
        return $this->lastTrackedAt;
    }

    public function getNextTrackingAt(): ?int
    {
        return $this->nextTrackingAt;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getShippingId(): string
    {
        return $this->shippingId;
    }

    public function getTrack(): string
    {
        return $this->track;
    }

    /**
     * @return LogisticStatus[]
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }

    public function addStatus(LogisticStatus $status): void
    {
        $this->statuses[] = $status;
    }

    /**
     * @param LogisticStatus[] $statuses
     * @return void
     */
    public function setStatuses(array $statuses): void
    {
        $this->statuses = $statuses;
    }

    /**
     * @return string[]
     */
    public function getNotificationsHashes(): array
    {
        return $this->notificationsHashes;
    }

    public function addNotification(LogisticStatus $status): void
    {
        $this->notificationsHashes[] = $status->getHash();
        //@todo create special request
    }

    public function getNotifiedAt(): ?int
    {
        return $this->notifiedAt;
    }

    public function setNotified(): void
    {
        $this->notifiedAt = time();
    }

    public function getStoppedAt(): ?int
    {
        return $this->stoppedAt;
    }

    public function setStoppedAt(?int $stoppedAt): void
    {
        $this->stoppedAt = $stoppedAt;
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
                'createdAt[>=]' => time() - 24 * 60 * 60 * 30 * 5, //трекаем заказы не старше 5 месяцев
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

    /**
     * @param string $segments
     * @param int $limit
     * @return array
     * @throws DatabaseException
     * @throws ReflectionException
     */
    public static function findForNotify(string $segments, int $limit = 3000): array
    {
        if (!empty($segments)) {
            $segments = explode(',', $segments);
        }

        $where = [
            'notifiedAt' => null,
        ];

        if (!empty($segments)) {
            $where['segment'] = $segments;
        }
        $where['LIMIT'] = $limit;
        $where['ORDER'] = 'notifiedAt';

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
        ], parent::schema());
    }

    public static function afterTableCreate(Medoo $db): void
    {
        $db->exec('CREATE INDEX `toUpdate` on ' . self::tableName() . ' (`createdAt`, `nextTrackingAt`, `stoppedAt`, `segment`)');
        $db->exec('CREATE INDEX `lastTrackedAt` on ' . self::tableName() . ' (`lastTrackedAt`)');
        DatabaseException::guard($db);
    }
}
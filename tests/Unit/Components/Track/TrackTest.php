<?php

namespace Leadvertex\Components\Track;

use DateTimeImmutable;
use Leadvertex\Components\MoneyValue\MoneyValue;
use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Logistic\Exceptions\LogisticStatusTooLongException;
use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Components\Logistic\Waybill\Waybill;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Exception\TrackException;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Track;
use Leadvertex\Helpers\LogisticTestCase;
use Mockery;
use XAKEPEHOK\EnumHelper\Exception\OutOfEnumException;

class TrackTest extends LogisticTestCase
{
    private Track $track;

    private Waybill $waybill;

    private PluginReference $pluginReference;

    protected function setUp(): void
    {
        $this->waybill = new Waybill(
            new \Leadvertex\Plugin\Components\Logistic\Waybill\Track('123456'),
            new MoneyValue(100)
        );
        $this->pluginReference = new PluginReference('1', 'alias', '2');
        $this->track = new Track($this->pluginReference, $this->waybill, 'shipping', '3');
    }

    public function testGetId(): void
    {
        $this->assertSame('3', $this->track->getId());
    }

    public function testGetPluginReferenceFields(): void
    {
        $this->assertSame('1', $this->track->getCompanyId());
        $this->assertSame('alias', $this->track->getPluginAlias());
        $this->assertSame('2', $this->track->getPluginId());
    }

    public function testGetTrack(): void
    {
        $this->assertSame('123456', $this->track->getTrack());
    }

    public function testGetShippingId(): void
    {
        $this->assertSame('shipping', $this->track->getShippingId());
    }

    public function testGetCreatedAt(): void
    {
        $this->assertSame(date('Y-m-d H:i'), date('Y-m-d H:i', $this->track->getCreatedAt()));
    }

    public function testGetSetNextTrackingAt(): void
    {
        $this->assertNull($this->track->getNextTrackingAt());

        $this->track->setNextTrackingAt(60);
        $this->assertSame(
            (new DateTimeImmutable('+60 minutes'))->format('Y-m-d H:i'),
            date('Y-m-d H:i', $this->track->getNextTrackingAt()),
        );
    }

    public function testGetSetLastTrackedAt(): void
    {
        $this->assertNull($this->track->getLastTrackedAt());

        $this->track->setLastTrackedAt();
        $this->assertSame(
            date('Y-m-d H:i', time()),
            date('Y-m-d H:i', $this->track->getLastTrackedAt()),
        );
    }

    public function testGetSetStoppedAt(): void
    {
        $this->assertNull($this->track->getStoppedAt());

        $this->track->setStoppedAt();
        $this->assertSame(
            date('Y-m-d H:i', time()),
            date('Y-m-d H:i', $this->track->getStoppedAt()),
        );
    }

    public function testGetSetNotifiedAt(): void
    {
        $this->assertNull($this->track->getNotifiedAt());

        $this->track->setNotified(new LogisticStatus(LogisticStatus::UNREGISTERED));
        $this->assertSame(
            date('Y-m-d H:i', time()),
            date('Y-m-d H:i', $this->track->getNotifiedAt()),
        );
    }

    public function testGetNotificationsHashes(): void
    {
        $status = new LogisticStatus(LogisticStatus::DELIVERED);
        $this->track->setNotified($status);

        $this->assertSame([$status->getHash()], $this->track->getNotificationsHashes());
    }

    public function testGetSetWaybill(): void
    {
        $this->assertEquals($this->waybill, $this->track->getWaybill());

        $expected = new Waybill(
            new \Leadvertex\Plugin\Components\Logistic\Waybill\Track('track22'),
            new MoneyValue(100)
        );
        $this->track->setWaybill($expected);

        $this->assertEquals($expected, $this->track->getWaybill());
    }

    public function addStatusDataProvider(): array
    {
        return [
            [
                [],
                new LogisticStatus(LogisticStatus::DELIVERED),
                [new LogisticStatus(LogisticStatus::DELIVERED)],
            ],
            [
                [new LogisticStatus(LogisticStatus::DELIVERED)],
                new LogisticStatus(LogisticStatus::DELIVERED),
                [
                    new LogisticStatus(LogisticStatus::DELIVERED),
                    new LogisticStatus(LogisticStatus::DELIVERED),
                ],
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::DELIVERED),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                ],
                new LogisticStatus(LogisticStatus::UNREGISTERED),
                [
                    new LogisticStatus(LogisticStatus::DELIVERED),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                    new LogisticStatus(LogisticStatus::UNREGISTERED),
                ],
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::ACCEPTED),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                    new LogisticStatus(LogisticStatus::RETURNED),
                ],
                new LogisticStatus(LogisticStatus::PENDING, 'pending'),
                [
                    new LogisticStatus(LogisticStatus::ACCEPTED),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                    new LogisticStatus(LogisticStatus::RETURNED),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'pending'),
                ],
            ],
        ];
    }

    /**
     * @param array $current
     * @param LogisticStatus $status
     * @param array $expected
     * @return void
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @throws TrackException
     * @dataProvider addStatusDataProvider
     */
    public function testAddStatus(array $current, LogisticStatus $status, array $expected): void
    {
        $track = Mockery::mock(Track::class)->makePartial();
        $track->shouldAllowMockingProtectedMethods();
        $track->shouldReceive('createNotification')->andReturnNull();

        $track->setStatuses($current);

        $track->addStatus($status);

        $this->assertEquals($expected, $track->getStatuses());
    }

    public function mergeStatusesDataProvider(): array
    {
        return [
            [
                [],
                [],
                [],
            ],
            [
                [],
                [new LogisticStatus(LogisticStatus::DELIVERED)],
                [new LogisticStatus(LogisticStatus::DELIVERED)],
            ],
            [
                [new LogisticStatus(LogisticStatus::DELIVERED)],
                [new LogisticStatus(LogisticStatus::DELIVERED)],
                [new LogisticStatus(LogisticStatus::DELIVERED)],
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                    new LogisticStatus(LogisticStatus::DELIVERED),
                ],
                [new LogisticStatus(LogisticStatus::DELIVERED)],
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                    new LogisticStatus(LogisticStatus::DELIVERED),
                ],
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::RETURNED, '', strtotime('2022-01-02')),
                ],
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, 'r_in_transit', strtotime('2022-01-03')),
                ],
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::RETURNED, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'r_in_transit', strtotime('2022-01-03')),
                ],
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::RETURNED, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'r_in_transit', strtotime('2022-01-02')),
                ],
                [
                    new LogisticStatus(LogisticStatus::ON_DELIVERY, 'r_in_transit', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::ON_DELIVERY, 'r_in_transit', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::ON_DELIVERY, 'r_in_transit', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::ON_DELIVERY, 'r_in_transit', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::ON_DELIVERY, 'r_in_transit', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::ON_DELIVERY, 'r_in_transit', strtotime('2022-01-03')),
                ],
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::RETURNED, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'r_in_transit', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'r_in_transit', strtotime('2022-01-03')),
                ],
            ],
        ];
    }

    /**
     * @param LogisticStatus[] $current
     * @param LogisticStatus[] $new
     * @param LogisticStatus[] $expected
     * @return void
     *
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @dataProvider mergeStatusesDataProvider
     */
    public function testMergeStatuses(array $current, array $new, array $expected): void
    {
        $this->assertEquals($expected, Track::mergeStatuses($current, $new));
    }

    public function testScheme(): void
    {
        $this->assertEquals([
            'companyId' => ['INT', 'NOT NULL'],
            'pluginAlias' => ['VARCHAR(255)', 'NOT NULL'],
            'pluginId' => ['INT', 'NOT NULL'],
            'track' => ['VARCHAR(50)'],
            'shippingId' => ['VARCHAR(50)'],
            'createdAt' => ['INT', 'NOT NULL'],
            'nextTrackingAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'lastTrackedAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'statuses' => ['MEDIUMTEXT'],
            'notificationsHashes' => ['TEXT'],
            'notifiedAt' => ['INT'],
            'stoppedAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'waybill' => ['TEXT'],
            'segment' => ['CHAR(1)'],
        ], Track::schema());
    }

}
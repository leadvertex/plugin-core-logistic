<?php

namespace Leadvertex\Components\Track;

use DateTimeImmutable;
use Leadvertex\Plugin\Components\Access\Registration\Registration;
use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Logistic\Exceptions\LogisticStatusTooLongException;
use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Components\SpecialRequestDispatcher\Models\SpecialRequestTask;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Track;
use Leadvertex\Helpers\LogisticTestCase;
use Mockery;
use XAKEPEHOK\EnumHelper\Exception\OutOfEnumException;

class TrackTest extends LogisticTestCase
{
    private Track $track;

    private PluginReference $pluginReference;

    protected function setUp(): void
    {
        $this->pluginReference = new PluginReference('1', 'alias', '1');
        $this->track = new Track($this->pluginReference, 'track', 'shiping', '1', true);
    }

    public function testGetPluginReferenceFields(): void
    {
        $this->assertSame('1', $this->track->getCompanyId());
        $this->assertSame('alias', $this->track->getPluginAlias());
        $this->assertSame('1', $this->track->getPluginId());
    }

    public function testGetOrderId(): void
    {
        $this->assertSame('1', $this->track->getOrderId());
    }

    public function testGetTrack(): void
    {
        $this->assertSame('track', $this->track->getTrack());
    }

    public function testGetShippingId(): void
    {
        $this->assertSame('shiping', $this->track->getShippingId());
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

    public function testGetCod(): void
    {
        $this->assertTrue($this->track->isCod());
        $track = new Track($this->pluginReference, 'track', 'shiping', '1', false);
        $this->assertFalse($track->isCod());
    }

    public function testGetNotificationsHashes(): void
    {
        $status = new LogisticStatus(LogisticStatus::DELIVERED);
        $this->track->setNotified($status);

        $this->assertSame([$status->getHash()], $this->track->getNotificationsHashes());
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
                [new LogisticStatus(LogisticStatus::DELIVERED)],
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
        ];
    }

    /**
     * @param array $current
     * @param LogisticStatus $status
     * @param array $expected
     * @return void
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     *
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
                ],
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, 'r_in_transit', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, 'DELIVERED_TO_SENDER', strtotime('2022-01-04')),
                ],
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::RETURNED, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'r_in_transit', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, 'DELIVERED_TO_SENDER', strtotime('2022-01-04')),
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
}
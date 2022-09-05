<?php

namespace Leadvertex\Services;

use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Logistic\Exceptions\LogisticStatusTooLongException;
use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Track;
use Leadvertex\Plugin\Core\Logistic\Services\LogisticStatusesResolverService;
use ReflectionException;
use Leadvertex\Helpers\LogisticTestCase;
use XAKEPEHOK\EnumHelper\Exception\OutOfEnumException;

class LogisticStatusesResolverServiceTest extends LogisticTestCase
{
    private Track $track;

    protected function setUp(): void
    {
        $pluginReference = new PluginReference('1', 'alias', '1');
        $this->track = new Track($pluginReference, 'track', 'shiping', '1', true);
    }

    public function getGetNextStatusForNotifyDataProvider(): array
    {
        return [
            [
                [],
                [],
                null,
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                ],
                [],
                new LogisticStatus(LogisticStatus::IN_TRANSIT),
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                ],
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                ],
                null,
            ],
            //проверка на вставку задним числом
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::DELIVERED, 'DELIVERED', strtotime('2022-01-04')),
                ],
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                ],
                new LogisticStatus(LogisticStatus::DELIVERED, 'DELIVERED', strtotime('2022-01-04')),
            ],
            /**
             * Проверка на отправку @see LogisticStatus::RETURNING_TO_SENDER
             */
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNED, 'RETURNED', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'RETURNED IN_TRANSIT', strtotime('2022-01-06')),
                ],
                [
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNED, 'RETURNED', strtotime('2022-01-04')),
                ],
                new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'RETURNED IN_TRANSIT', strtotime('2022-01-06')),
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNED, 'RETURNED', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'RETURNED IN_TRANSIT', strtotime('2022-01-06')),
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, 'DELIVERED_TO_SENDER IN_TRANSIT', strtotime('2022-01-06')),
                ],
                [
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNED, 'RETURNED', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'RETURNED IN_TRANSIT', strtotime('2022-01-06')),
                ],
                new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, 'DELIVERED_TO_SENDER IN_TRANSIT', strtotime('2022-01-06')),
            ],
        ];
    }

    /**
     * @param LogisticStatus[] $statuses
     * @param LogisticStatus[] $sentStatuses
     * @param LogisticStatus|null $expected
     * @return void
     *
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @dataProvider getGetNextStatusForNotifyDataProvider
     */
    public function testGetNextStatusForNotify(array $statuses, array $sentStatuses, ?LogisticStatus $expected): void
    {
        $this->track->setStatuses($statuses);
        foreach ($sentStatuses as $sentStatus) {
            $this->track->setNotified($sentStatus);
        }
        $service = new LogisticStatusesResolverService($this->track);
        $actual = $service->getLastStatusForNotify();

        if ($expected === null) {
            $this->assertNull($expected);
        } else {
            $this->assertEquals($expected, $actual);
        }
    }

    public function getStatusesForSortDataProvider(): array
    {
        return [
            [
                [
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                ],
                [
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, '', strtotime('2022-01-02')),
                ],
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                ],
                [
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                ],
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::REGISTERED, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::CREATED, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-06')),
                    new LogisticStatus(LogisticStatus::ACCEPTED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::PACKED, '', strtotime('2022-01-05')),
                    new LogisticStatus(LogisticStatus::PENDING, '', strtotime('2022-01-09')),
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-07')),
                    new LogisticStatus(LogisticStatus::ON_DELIVERY, '', strtotime('2022-01-08')),
                    new LogisticStatus(LogisticStatus::PAID, '', strtotime('2022-01-11')),
                    new LogisticStatus(LogisticStatus::DELIVERED, '', strtotime('2022-01-10')),
                    new LogisticStatus(LogisticStatus::RETURNED, '', strtotime('2022-01-12')),
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, '', strtotime('2022-01-14')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, '', strtotime('2022-01-13')),
                ],
                [
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::CREATED, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::REGISTERED, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::ACCEPTED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::PACKED, '', strtotime('2022-01-05')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-06')),
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-07')),
                    new LogisticStatus(LogisticStatus::ON_DELIVERY, '', strtotime('2022-01-08')),
                    new LogisticStatus(LogisticStatus::PENDING, '', strtotime('2022-01-09')),
                    new LogisticStatus(LogisticStatus::DELIVERED, '', strtotime('2022-01-10')),
                    new LogisticStatus(LogisticStatus::PAID, '', strtotime('2022-01-11')),
                    new LogisticStatus(LogisticStatus::RETURNED, '', strtotime('2022-01-12')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, '', strtotime('2022-01-13')),
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, '', strtotime('2022-01-14')),
                ],
            ],
            [
                [],
                [],
            ],
        ];
    }

    /**
     * @param LogisticStatus[] $statuses
     * @param LogisticStatus[] $expected
     * @return void
     * @throws ReflectionException
     *
     * @dataProvider getStatusesForSortDataProvider
     */
    public function testSort(array $statuses, array $expected): void
    {
        $this->track->setStatuses($statuses);
        $service = new LogisticStatusesResolverService($this->track);

        $sortMethod = self::getMethod('sort');
        $actual = $sortMethod->invoke($service, $statuses);

        $this->assertEquals($expected, $actual);
    }

}
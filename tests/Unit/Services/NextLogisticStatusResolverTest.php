<?php

namespace Unit\Services;

use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Track;
use Leadvertex\Plugin\Core\Logistic\Services\NextLogisticStatusResolver;
use PHPUnit\Framework\TestCase;

class NextLogisticStatusResolverTest extends TestCase
{
    private Track $trackCod;
    private Track $trackNoCod;

    private LogisticStatus $unregistered;
    private LogisticStatus $created;
    private LogisticStatus $registered;
    private LogisticStatus $accepted;
    private LogisticStatus $packed;
    private LogisticStatus $inTransit;
    private LogisticStatus $arrived;
    private LogisticStatus $onDelivery;
    private LogisticStatus $pending;
    private LogisticStatus $delivered;
    private LogisticStatus $paid;
    private LogisticStatus $returned;
    private LogisticStatus $returningToSender;
    private LogisticStatus $deliveredToSender;

    protected function setUp(): void
    {
        $this->unregistered = new LogisticStatus(LogisticStatus::UNREGISTERED, 'UNREGISTERED', strtotime('2022-09-01 00:00:01'));
        $this->created = new LogisticStatus(LogisticStatus::CREATED, 'CREATED', strtotime('2022-09-01 00:00:02'));
        $this->registered = new LogisticStatus(LogisticStatus::REGISTERED, 'REGISTERED', strtotime('2022-09-01 00:00:03'));
        $this->accepted = new LogisticStatus(LogisticStatus::ACCEPTED, 'ACCEPTED', strtotime('2022-09-01 00:00:04'));
        $this->packed = new LogisticStatus(LogisticStatus::PACKED, 'PACKED', strtotime('2022-09-01 00:00:05'));
        $this->inTransit = new LogisticStatus(LogisticStatus::IN_TRANSIT, 'IN_TRANSIT', strtotime('2022-09-01 00:00:06'));
        $this->arrived = new LogisticStatus(LogisticStatus::ARRIVED, 'ARRIVED', strtotime('2022-09-01 00:00:07'));
        $this->onDelivery = new LogisticStatus(LogisticStatus::ON_DELIVERY, 'ON_DELIVERY', strtotime('2022-09-01 00:00:08'));
        $this->pending = new LogisticStatus(LogisticStatus::PENDING, 'PENDING', strtotime('2022-09-01 00:00:09'));
        $this->paid = new LogisticStatus(LogisticStatus::PAID, 'PAID', strtotime('2022-09-01 00:00:11'));
        $this->returned = new LogisticStatus(LogisticStatus::RETURNED, 'RETURNED', strtotime('2022-09-01 00:00:12'));
        $this->delivered = new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'DELIVERED', strtotime('2022-09-01 00:00:10'));
        $this->returningToSender = new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'RETURNING_TO_SENDER', strtotime('2022-09-01 00:00:13'));
        $this->deliveredToSender = new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, 'DELIVERED_TO_SENDER', strtotime('2022-09-01 00:00:14'));


        $this->trackCod = new Track(new PluginReference('1', 'rp_pugin', '1'), 'track_1', 'shipping_1', true);
        $this->trackNoCod = new Track(new PluginReference('1', 'rp_pugin', '1'), 'track_2', 'shipping_2', false);
    }

    public function testUnregister(): void
    {
        $unregistered = new LogisticStatus(LogisticStatus::UNREGISTERED, 'UNREGISTERED', strtotime('2022-09-01 00:00:10'));

        $this->trackCod->setStatuses([
            new LogisticStatus(LogisticStatus::CREATED, 'DELIVERED', strtotime('2022-09-01 00:00:00')),
            $unregistered,
            new LogisticStatus(LogisticStatus::RETURNED, 'RETURNED', strtotime('2022-09-01 00:00:10')),
        ]);

        $resolver = new NextLogisticStatusResolver($this->trackCod);
        $this->assertSame($unregistered->getHash(), $resolver->getNextLogisticStatus()->getHash());
        $this->trackCod->addNotification($resolver->getNextLogisticStatus());
        $this->assertNull($resolver->getNextLogisticStatus());
    }

    public function testDelivered(): void
    {
        $delivered = new LogisticStatus(LogisticStatus::DELIVERED, 'DELIVERED', strtotime('2022-09-01 00:00:10'));
        $paid = new LogisticStatus(LogisticStatus::PAID, 'PAID', strtotime('2022-09-01 00:01:10'));

        $this->trackCod->setStatuses([
            $this->created,
            $this->inTransit,
            $this->arrived,
            $delivered,
            $paid,
        ]);

        $resolver = new NextLogisticStatusResolver($this->trackCod);
        $this->assertSame($paid->getHash(), $resolver->getNextLogisticStatus()->getHash());
        $this->trackCod->addNotification($resolver->getNextLogisticStatus());
        $this->assertNull($resolver->getNextLogisticStatus());

        $this->trackNoCod->setStatuses([
            $this->created,
            $this->inTransit,
            $this->arrived,
            $delivered,
            $paid,
        ]);

        $resolver = new NextLogisticStatusResolver($this->trackNoCod);
        $this->assertSame($delivered->getHash(), $resolver->getNextLogisticStatus()->getHash());
        $this->trackNoCod->addNotification($resolver->getNextLogisticStatus());
        $this->assertNull($resolver->getNextLogisticStatus());
    }

    public function testReturned(): void
    {
        $this->trackCod->setStatuses([
            $this->created,
            $this->inTransit,
            $this->arrived,
            $this->returned,
            $this->paid,
        ]);
        $resolver = new NextLogisticStatusResolver($this->trackCod);
        $this->assertSame($this->paid->getHash(), $resolver->getNextLogisticStatus()->getHash());

        $this->trackCod->setStatuses([
            $this->created,
            $this->inTransit,
            $this->arrived,
            $this->returned,
            $this->deliveredToSender,
        ]);
        $resolver = new NextLogisticStatusResolver($this->trackCod);
        $this->assertSame($this->returned->getHash(), $resolver->getNextLogisticStatus()->getHash());
        $this->trackCod->addNotification($this->returned);

        $this->assertSame($this->deliveredToSender->getHash(), $resolver->getNextLogisticStatus()->getHash());
        $this->trackCod->addNotification($this->deliveredToSender);
        $this->assertNull($resolver->getNextLogisticStatus());

        $this->trackCod->notificationsHashes = [];
        $this->trackCod->setStatuses([
            $this->created,
            $this->inTransit,
            $this->arrived,
            $this->returned,
        ]);
        $resolver = new NextLogisticStatusResolver($this->trackCod);
        $this->assertSame($this->returned->getHash(), $resolver->getNextLogisticStatus()->getHash());
        $this->trackCod->addNotification($this->returned);
        $this->assertNull($resolver->getNextLogisticStatus());

        $arrived = new LogisticStatus(LogisticStatus::ARRIVED, 'ARRIVED');
        $this->trackCod->notificationsHashes = [];
        $this->trackCod->setStatuses([
            $this->created,
            $this->inTransit,
            $this->arrived,
            $this->returned,
        ]);
        $resolver = new NextLogisticStatusResolver($this->trackCod);
        $this->assertSame($this->returned->getHash(), $resolver->getNextLogisticStatus()->getHash());
        $this->trackCod->addNotification($this->returned);

        $this->trackCod->addStatus($arrived);
        $actual = $resolver->getNextLogisticStatus();
        $this->assertSame(md5($arrived->getText() . $arrived->getTimestamp()), md5($actual->getText() . $actual->getTimestamp()));
        $this->assertSame(LogisticStatus::RETURNING_TO_SENDER, $actual->getCode());

        $this->trackCod->addNotification($actual);
        $this->assertNull($resolver->getNextLogisticStatus());
    }

}
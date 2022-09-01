<?php

namespace Leadvertex\Plugin\Core\Logistic\Commands;

use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Core\Logistic\Components\Notification\Notification;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Track;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends Command
{
    public function __construct()
    {
        parent::__construct('app:test');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        Connector::setReference(new PluginReference('1', 'plugin', '1'));
        $tracks = Track::findForTracking();
        $notifications = Notification::findForNotify();


        $track = new Track('track_2', 'shipping_1');
        $track->addStatus(new LogisticStatus(
            LogisticStatus::ACCEPTED,
            'accepted',
        ));
        $track->save();

        $t = Track::findByCondition(['track' => 'track_2']);

        /** @var Track $v */
        foreach ($t as $v) {
            $notifications = Notification::findByTrackId($v->getId());

            $s = $v->getStatuses();


            foreach ($s as $value) {
                echo $value->getHash() . PHP_EOL;
            }
        }

        return self::SUCCESS;
    }
}
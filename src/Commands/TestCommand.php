<?php

namespace Leadvertex\Plugin\Core\Logistic\Commands;

use Leadvertex\Components\Address\Address;
use Leadvertex\Components\Address\Location;
use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Logistic\Components\OpeningHours;
use Leadvertex\Plugin\Components\Logistic\LogisticOffice;
use Leadvertex\Plugin\Components\Logistic\Waybill\Waybill;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Track;
use Spatie\OpeningHours\Day;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends Command
{
    public function __construct(string $name = null)
    {
        parent::__construct('test');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $track = Track::findById('547a662e-6398-4a0e-92d8-1151351b86ba');




        $waybill = new Waybill(new \Leadvertex\Plugin\Components\Logistic\Waybill\Track('123456'));

        $p = new PluginReference('1', 'alias', '1');
        $track = new Track($p, $waybill, 'shiping', '1', true);



        $track->setWaybill($waybill);

        $office = new LogisticOffice(new Address('region', 'city',
            'a_1', 'a_2', '123456', 'RU', new Location('1.1', '1.2')),
            ['79887731111'],
            new OpeningHours([
                Day::MONDAY => ['09:00-12:00', '13:00-18:00'],
                Day::TUESDAY => ['09:00-12:00', '13:00-18:00'],
                Day::WEDNESDAY => ['09:00-12:00'],
                Day::THURSDAY => ['09:00-12:00', '13:00-18:00'],
                Day::FRIDAY => ['09:00-12:00', '13:00-20:00'],
                Day::SATURDAY => ['09:00-12:00', '13:00-16:00'],
                Day::SUNDAY => [],
            ])
        );

        $track->save();


        return self::SUCCESS;
    }
}
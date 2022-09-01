<?php

namespace Leadvertex\Plugin\Core\Logistic\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NotifyCommand extends Command
{
    public function __construct()
    {
        parent::__construct('tracking:notify');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}
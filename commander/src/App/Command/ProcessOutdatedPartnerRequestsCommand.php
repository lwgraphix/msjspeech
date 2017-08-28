<?php

namespace App\Command;

use App\Executable\ProcessOutdatedPartnerRequestsExecutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessOutdatedPartnerRequestsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('process:partner_requests')
            ->setDescription('Declines all partner requests by timeout')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $executable = new ProcessOutdatedPartnerRequestsExecutable($input, $output);
        $executable->run();
    }
}
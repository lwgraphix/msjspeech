<?php

namespace App\Command;

use App\Executable\DbMigrateExecutable;
use App\Executable\ProcessOutdatedPartnerRequestsExecutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DbMigrateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('db:migrate')
            ->setDescription('Copies your current database to another database')
            ->addArgument('dbname', InputArgument::REQUIRED, 'New database name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $executable = new DbMigrateExecutable($input, $output);
        $executable->run();
    }
}
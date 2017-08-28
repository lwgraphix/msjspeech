<?php

namespace App\Executable;

use App\Util\MergeInsert;
use App\Util\MySQL;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Util\Configuration;

class BaseExecutable
{
    private $in;
    private $out;

    public function getProjectDir()
    {
        return __DIR__ . '/../../../../src/App/';
    }

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->in = $input;
        $this->out = $output;
    }

    public function log($message)
    {
        if (!$this->getInput()->hasOption('silent') || $this->getInput()->getOption('silent') === false)
        {
            $this->getOutput()->writeln('['.date('d.m.Y H:i:s').'] '.$message);
        }
    }

    public function getInput()
    {
        return $this->in;
    }

    public function getOutput()
    {
        return $this->out;
    }

}
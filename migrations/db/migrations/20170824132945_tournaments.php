<?php

use Phinx\Migration\AbstractMigration;

class Tournaments extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('tournaments');
        $table
            ->addColumn('name', 'string', ['null' => false])
            ->addColumn('event_start', 'timestamp', ['null' => false])
            ->addColumn('entry_deadline', 'timestamp', ['null' => false])
            ->addColumn('drop_deadline', 'timestamp', ['null' => false])

            ->create();
    }

    public function down()
    {
        $this->table('tournaments')->drop();
    }
}

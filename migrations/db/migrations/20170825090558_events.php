<?php

use Phinx\Migration\AbstractMigration;

class Events extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('events');
        $table
            ->addColumn('tournament_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['null' => false])
            ->addColumn('type', 'integer', ['null' => false])
            ->addColumn('cost', 'float', ['null' => false])
            ->addColumn('drop_fee_cost', 'float', ['null' => false])
            ->create();
    }

    public function down()
    {
        $this->table('events')->drop();
    }
}

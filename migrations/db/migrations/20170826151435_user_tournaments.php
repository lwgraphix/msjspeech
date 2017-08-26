<?php

use Phinx\Migration\AbstractMigration;

class UserTournaments extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('user_tournaments');
        $table
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('tournament_id', 'integer', ['null' => false])
            ->addColumn('event_id', 'integer', ['null' => false])
            ->addColumn('partner_id', 'integer', ['null' => true])
            ->addColumn('status', 'integer', ['null' => false])
            ->create();
    }

    public function down()
    {
        $this->table('user_tournaments')->drop();
    }
}

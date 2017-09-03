<?php

use Phinx\Migration\AbstractMigration;

class TournamentGroups extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('tournament_groups');
        $table
            ->addColumn('tournament_id', 'integer', ['null' => false])
            ->addColumn('group_id', 'integer', ['null' => false])
            ->addIndex('group_id')
            ->addIndex('tournament_id')
            ->addIndex(['group_id', 'tournament_id'])
            ->create();
    }

    public function down()
    {
        $this->table('tournament_groups')->drop();
    }
}

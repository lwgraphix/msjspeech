<?php

use Phinx\Migration\AbstractMigration;

class Attributes extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('attributes');
        $table
            ->addColumn('group', 'integer', ['null' => false])
            ->addColumn('label', 'string', ['null' => false])
            ->addColumn('placeholder', 'string', ['null' => true])
            ->addColumn('help_text', 'string', ['null' => true])
            ->addColumn('type', 'integer', ['null' => false])
            ->addColumn('data', 'string', ['limit' => 8096, 'null' => true])
            ->addColumn('required', 'integer', ['null' => false, 'default' => 1])
            ->addColumn('editable', 'integer', ['null' => false, 'default' => 1])
            ->addColumn('tournament_id', 'integer', ['null' => true])
            ->addIndex('group')
            ->addIndex(['group', 'tournament_id'])
            ->create();
    }

    public function down()
    {
        $this->table('attributes')->drop();
    }
}

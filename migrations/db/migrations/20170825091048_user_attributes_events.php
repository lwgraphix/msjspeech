<?php

use Phinx\Migration\AbstractMigration;

class UserAttributesEvents extends AbstractMigration
{
    public function change()
    {
        $this->table('user_attributes')
            ->addColumn('user_tournament_id', 'integer', ['null' => true])
            ->addIndex('user_tournament_id')
            ->update();
    }
}

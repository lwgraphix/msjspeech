<?php

use Phinx\Migration\AbstractMigration;

class UserAttributesEvents extends AbstractMigration
{
    public function change()
    {
        $this->table('user_attributes')
            ->addColumn('event_id', 'integer', ['null' => true])
            ->addIndex('event_id')
            ->update();
    }
}

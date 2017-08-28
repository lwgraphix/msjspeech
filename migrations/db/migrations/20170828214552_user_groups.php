<?php

use Phinx\Migration\AbstractMigration;

class UserGroups extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('groups');
        $table
            ->addColumn('name', 'string', ['null' => false])
            ->create();
    }

    public function down()
    {
        $this->table('groups')->drop();
    }
}

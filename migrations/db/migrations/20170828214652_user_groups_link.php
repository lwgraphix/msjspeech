<?php

use Phinx\Migration\AbstractMigration;

class UserGroupsLink extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('user_groups');
        $table
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('group_id', 'integer', ['null' => false])
            ->addIndex('user_id')
            ->addIndex('group_id')
            ->addIndex(['user_id', 'group_id'])
            ->create();
    }

    public function down()
    {
        $this->table('user_groups')->drop();
    }
}

<?php

use Phinx\Migration\AbstractMigration;

class User extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('users');
        $table
            ->addColumn('email', 'string', ['null' => false])
            ->addColumn('username', 'string', ['null' => true])
            ->addColumn('password', 'string', ['null' => false])
            ->addColumn('first_name', 'string', ['null' => false])
            ->addColumn('last_name', 'string', ['null' => false])
            ->addColumn('parent_first_name', 'string', ['null' => true])
            ->addColumn('parent_last_name', 'string', ['null' => true])
            ->addColumn('parent_email', 'string', ['null' => true])
            ->addColumn('role', 'integer', ['null' => false, 'default' => 0])
        ->create();
    }

    public function down()
    {
        $this->table('users')->drop();
    }
}

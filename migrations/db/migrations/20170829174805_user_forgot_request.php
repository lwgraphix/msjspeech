<?php

use Phinx\Migration\AbstractMigration;

class UserForgotRequest extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('restore_users');
        $table
            ->addColumn('hash', 'string', ['null' => false])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('status', 'integer', ['null' => false])
            ->addColumn('request_time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex('hash')
            ->create();
    }

    public function down()
    {
        $this->table('restore_users')->drop();
    }
}

<?php

use Phinx\Migration\AbstractMigration;

class SystemSettings extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('system_settings');
        $table
            ->addColumn('label', 'string', ['null' => false])
            ->addColumn('name', 'string', ['null' => false])
            ->addColumn('value', 'string', ['null' => true])
            ->addColumn('boolean', 'integer', ['null' => false])
            ->create();
    }

    public function down()
    {
        $this->table('system_settings')->drop();
    }
}

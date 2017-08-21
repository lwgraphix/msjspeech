<?php

use Phinx\Migration\AbstractMigration;

class Attachments extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('attachments');
        $table
            ->addColumn('path', 'integer', ['null' => false])
            ->create();
    }

    public function down()
    {
        $this->table('attachments')->drop();
    }
}

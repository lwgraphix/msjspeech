<?php

use Phinx\Migration\AbstractMigration;

class EmailTemplates extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('email_templates', ['id' => false, 'primary_key' => 'type']);
        $table
            ->addColumn('type', 'integer', ['null' => false])
            ->addColumn('subject', 'string', ['null' => true])
            ->addColumn('content', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM, 'null' => true])
            ->create();
    }

    public function down()
    {
        $this->table('email_templates')->drop();
    }
}

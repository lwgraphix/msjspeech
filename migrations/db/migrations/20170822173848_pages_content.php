<?php

use Phinx\Migration\AbstractMigration;

class PagesContent extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('pages_content');
        $table
            ->addColumn('page_id', 'integer', ['null' => false])
            ->addColumn('content', 'text', ['null' => false, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG])
            ->addColumn('author_id', 'string', ['null' => false])
            ->addColumn('timestamp', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('reason', 'string', ['null' => true])
            ->create();

        $table->insert([
            ['page_id' => 1, 'content' => 'Welcome!', 'author_id' => '1'],
            ['page_id' => 2, 'content' => 'Terms', 'author_id' => '1'],
        ])->saveData();
    }

    public function down()
    {
        $this->table('pages_content')->drop();
    }
}

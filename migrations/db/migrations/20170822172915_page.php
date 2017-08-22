<?php

use Phinx\Migration\AbstractMigration;

class Page extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('pages');
        $table
            ->addColumn('category_id', 'integer', ['null' => false, 'default' => -1])
            ->addColumn('slug', 'string', ['null' => false])
            ->addColumn('name', 'string', ['null' => true])
            ->addColumn('public', 'integer', ['null' => false])
            ->create();
    }

    public function down()
    {
        $this->table('pages')->drop();
    }
}

<?php

use Phinx\Migration\AbstractMigration;

class PageCategory extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('pages_category');
        $table
            ->addColumn('parent_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['null' => false])
            ->create();
    }

    public function down()
    {
        $this->table('pages_category')->drop();
    }
}

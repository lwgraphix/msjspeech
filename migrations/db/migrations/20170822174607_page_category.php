<?php

use Phinx\Migration\AbstractMigration;

class PageCategory extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('pages_category');
        $table
            ->addColumn('name', 'string', ['null' => false])
            ->create();
    }

    public function down()
    {
        $this->table('pages_category')->drop();
    }
}

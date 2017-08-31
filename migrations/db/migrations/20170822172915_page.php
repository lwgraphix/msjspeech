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

        $table->insert([
            ['category_id' => 0, 'slug' => 'home', 'name' => 'Home page', 'public' => 1],
            ['category_id' => 0, 'slug' => 'terms', 'name' => 'Terms', 'public' => 1]
        ])->saveData();
    }

    public function down()
    {
        $this->table('pages')->drop();
    }
}

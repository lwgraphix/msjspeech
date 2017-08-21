<?php

use Phinx\Migration\AbstractMigration;

class UserAttributes extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('user_attributes', ['id' => false, 'primary_key' => 'id']);
        $table
            ->addColumn('id', 'integer', ['signed' => false, 'identity' => true])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('attribute_id', 'integer', ['null' => false])
            ->addColumn('value', 'string', ['null' => true])
            ->addIndex('user_id')
            ->create();
    }

    public function down()
    {
        $this->table('user_attributes')->drop();
    }
}

<?php

use Phinx\Migration\AbstractMigration;

class TransactionHistory extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('transaction_history');
        $table
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('amount', 'float', ['signed' => true, 'null' => false])
            ->addColumn('description', 'string', ['null' => false])
            ->addColumn('timestamp', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }

    public function down()
    {
        $this->table('transaction_history')->drop();
    }
}

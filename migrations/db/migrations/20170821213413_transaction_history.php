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
            ->addColumn('timestamp', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('type', 'integer', ['null' => false])
            ->addColumn('creator_id', 'integer', ['null' => false])
            ->addColumn('memo_1', 'string', ['null' => false])
            ->addColumn('memo_2', 'string', ['null' => true])
            ->addColumn('memo_3', 'string', ['null' => true])
            ->addColumn('memo_4', 'string', ['null' => true])
            ->addColumn('memo_5', 'string', ['null' => true])
            ->addColumn('event_id', 'integer', ['null' => true])
            ->addIndex('type')
            ->addIndex('user_id')
            ->addIndex('event_id')
            ->addIndex(['type', 'event_id'])
            ->create();
    }

    public function down()
    {
        $this->table('transaction_history')->drop();
    }
}

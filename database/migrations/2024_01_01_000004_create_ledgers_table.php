<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRITICAL: This table is APPEND-ONLY.
     * Never update or delete rows in the ledger.
     * It serves as the immutable financial audit trail.
     */
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('account_id')->index();
            $table->uuid('transaction_id')->index();
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 20, 2);
            $table->decimal('balance_after', 20, 2);
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('restrict');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('restrict');
        });

        // Ensure positive amounts and non-negative balance_after (via raw SQL — Blueprint::check not available)
        \DB::statement('ALTER TABLE ledgers ADD CONSTRAINT ledgers_amount_positive CHECK (amount > 0)');
        \DB::statement('ALTER TABLE ledgers ADD CONSTRAINT ledgers_balance_after_non_negative CHECK (balance_after >= 0)');

        // PostgreSQL trigger to prevent UPDATE/DELETE on ledger (append-only enforcement)
        \DB::statement("
            CREATE OR REPLACE FUNCTION prevent_ledger_mutation()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'Ledger is append-only. UPDATE and DELETE operations are not permitted.';
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        \DB::statement("
            CREATE TRIGGER ledger_no_update
            BEFORE UPDATE ON ledgers
            FOR EACH ROW EXECUTE FUNCTION prevent_ledger_mutation();
        ");

        \DB::statement("
            CREATE TRIGGER ledger_no_delete
            BEFORE DELETE ON ledgers
            FOR EACH ROW EXECUTE FUNCTION prevent_ledger_mutation();
        ");
    }

    public function down(): void
    {
        \DB::statement('DROP TRIGGER IF EXISTS ledger_no_update ON ledgers;');
        \DB::statement('DROP TRIGGER IF EXISTS ledger_no_delete ON ledgers;');
        \DB::statement('DROP FUNCTION IF EXISTS prevent_ledger_mutation();');
        Schema::dropIfExists('ledgers');
    }
};

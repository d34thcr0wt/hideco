<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Add missing columns to borrowers table
        Schema::table('borrowers', function (Blueprint $table) {
            if (!Schema::hasColumn('borrowers', 'full_name')) {
                $table->string('full_name')->nullable();
                // Update existing records to set full_name
                DB::statement("UPDATE borrowers SET full_name = CONCAT(first_name, ' ', last_name) WHERE full_name IS NULL");
            }
            if (!Schema::hasColumn('borrowers', 'added_by')) {
                $table->unsignedBigInteger('added_by')->nullable();
                $table->foreign('added_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        // Add missing columns to loans table
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'loan_number')) {
                $table->string('loan_number')->nullable();
            }
            if (!Schema::hasColumn('loans', 'from_this_account')) {
                $table->string('from_this_account')->nullable();
            }
            if (!Schema::hasColumn('loans', 'balance')) {
                $table->decimal('balance', 10, 2)->default(0);
                // Update existing records to set balance
                DB::statement("UPDATE loans SET balance = principal_amount WHERE balance = 0");
            }
        });

        // Add missing columns to expenses table
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'from_this_account')) {
                $table->string('from_this_account')->nullable();
            }
        });

        // Add missing columns to loan_agreement_forms table
        Schema::table('loan_agreement_forms', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_agreement_forms', 'loan_agreement_file_path')) {
                $table->string('loan_agreement_file_path')->nullable();
            }
            if (!Schema::hasColumn('loan_agreement_forms', 'activate_loan_agreement_form')) {
                $table->boolean('activate_loan_agreement_form')->default(false);
            }
        });

        // Add missing columns to loan_settlement_forms table
        Schema::table('loan_settlement_forms', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_settlement_forms', 'loan_settlement_file_path')) {
                $table->string('loan_settlement_file_path')->nullable();
            }
        });

        // Add missing columns to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'transaction_number')) {
                $table->string('transaction_number')->nullable();
            }
        });

        // Add missing columns to wallets table
        Schema::table('wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('wallets', 'amount')) {
                $table->decimal('amount', 10, 2)->default(0);
                // Update existing records to set amount
                DB::statement("UPDATE wallets SET amount = balance WHERE amount = 0");
            }
        });
    }

    public function down()
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn(['full_name', 'added_by']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['loan_number', 'from_this_account', 'balance']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('from_this_account');
        });

        Schema::table('loan_agreement_forms', function (Blueprint $table) {
            $table->dropColumn(['loan_agreement_file_path', 'activate_loan_agreement_form']);
        });

        Schema::table('loan_settlement_forms', function (Blueprint $table) {
            $table->dropColumn('loan_settlement_file_path');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_number');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
}; 
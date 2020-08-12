<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned();
            $table->integer('bank_id')->unsigned();
            $table->decimal('amount', 13, 2)->default(0);
            $table->string("bank_account_number");
            $table->string("reason")->nullable();
            $table->string("bank_account_name");
            $table->string("recipient_code");
             $table->string("reference")->nullable();
             $table->enum("status", ['drafted', 'pending', 'failed', 'successful'])->default('drafted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}

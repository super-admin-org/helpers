<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScaffoldsTable extends Migration
{
    public function up()
    {
        $connection = config('admin.database.connection') ?: config('database.default');

        $scaffoldTable = 'helper_scaffolds';
        $scaffoldDetailsTable = 'helper_scaffold_details';

        Schema::connection($connection)->create($scaffoldTable, function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->string('model_name')->nullable();
            $table->string('controller_name')->nullable();
            $table->json('create_options')->nullable();
            $table->string('primary_key')->default('id');
            $table->boolean('timestamps')->default(true);
            $table->boolean('soft_deletes')->default(false);
            $table->timestamps();
        });

        Schema::connection($connection)->create($scaffoldDetailsTable, function (Blueprint $table) use ($scaffoldTable) {
            $table->id();
            $table->foreignId('scaffold_id')
                ->constrained($scaffoldTable)
                ->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->boolean('nullable')->default(false);
            $table->string('key')->nullable();
            $table->string('default')->nullable();
            $table->string('comment')->nullable();
            $table->integer('order')->default(0);
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
        $connection = config('admin.database.connection') ?: config('database.default');
        Schema::connection($connection)->dropIfExists( 'helper_scaffolds');
        Schema::connection($connection)->dropIfExists( 'helper_scaffold_details');
    }
}


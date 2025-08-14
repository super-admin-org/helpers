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
            $table->string('name')->nullable()->comment('table column name');
            $table->string('type')->nullable()->comment('table column type');
            $table->boolean('nullable')->default(false)->comment('table column nullable or not');
            $table->string('key')->nullable()->comment('table column key null index, unique');
            $table->string('default')->nullable()->comment('table column default value');
            $table->string('comment')->nullable()->comment('table column comment or description');
            $table->integer('order')->default(0)->comment('table column order');
            $table->string('input_type', 40)->nullable()->comment('text, email, date, radio, select, checkbox, file, textarea...');        // text, email, date, radio, select, checkbox, file, textarea...
            $table->string('options_source', 191)->nullable()->comment('static OR FQCN like App\Models\User');   // 'static' OR FQCN like 'App\Models\User'
            $table->string('options_value_col', 191)->nullable()->comment('static: "male,female,other" | model: "id"');// static: "male,female,other" | model: "id"
            $table->string('options_label_col', 191)->nullable()->comment('static: "Male,Female,Other"  | model: "name"');// static: "Male,Female,Other"  | model: "name"
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
        Schema::connection($connection)->dropIfExists('helper_scaffolds');
        Schema::connection($connection)->dropIfExists('helper_scaffold_details');
    }
}


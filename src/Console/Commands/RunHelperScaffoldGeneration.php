<?php
/**
 * Author: Talemul Islam
 * Website: https://talemul.com
 */

namespace SuperAdmin\Admin\Helpers\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SuperAdmin\Admin\Helpers\Controllers\ScaffoldController;
use App\Services\ScaffoldService;

class RunHelperScaffoldGeneration extends Command
{
    protected $signature = 'scaffold:generate-code';
    protected $description = 'Run saveScaffold() for all records in helper_scaffolds';

    public function handle()
    {
       // $service = new ScaffoldService();

        $controller = new ScaffoldController();
        $scaffolds = DB::table('helper_scaffolds')->get();

        foreach ($scaffolds as $scaffold) {
            $details = DB::table('helper_scaffold_details')
                ->where('scaffold_id', $scaffold->id)
                ->orderBy('order')
                ->get();

            // Build fields structure as expected by the form
            $fields = $details->map(fn($d) => [
                'name' => $d->name,
                'type' => $d->type,
                'nullable' => $d->nullable,
                'key' => $d->key,
                'default' => $d->default,
                'comment' => $d->comment,
            ])->toArray();

            // Build expected request payload
            $payload = [
                'table_name' => $scaffold->table_name,
                'model_name' => $scaffold->model_name,
                'controller_name' => $scaffold->controller_name,
                'primary_key' => $scaffold->primary_key,
                'create' => json_decode($scaffold->create_options, true) ?? [],
                'timestamps' => $scaffold->timestamps ? 'on' : null, // Laravel form behavior
                'soft_deletes' => $scaffold->soft_deletes ? 'on' : null,
                'fields' => $fields,
            ];

            $request = new Request($payload);

            // Use reflection to call protected saveScaffold()
            $method = new \ReflectionMethod($controller, 'saveScaffold');
            $method->setAccessible(true);
            $model = \SuperAdmin\Admin\Helpers\Model\Scaffold::firstWhere('table_name', $scaffold->table_name);
            $method->invoke($controller, $request, $model, true);
            // Pass Request + Scaffold model
            $this->info("✅ Generated: {$scaffold->table_name}");
        }
    }
}

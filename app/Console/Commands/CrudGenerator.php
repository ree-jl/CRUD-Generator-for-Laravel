<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CrudGenerator extends Command
{
	protected $signature = 'make:crud {name : The name of the model} {--option= : List of fields for the model and migration}';

	protected $description = 'Generate CRUD operations for a specified model';

	public function handle()
	{
		$name = $this->argument('name');
		$fields = $this->option('option') ? $this->parseFields($this->option('option')) : [];

		$this->generateModel($name, $fields);
		$this->generateController($name);
		$this->addRoute($name);
		$this->generateMigration($name, $fields);
		$this->generateViews($name);

		$this->info('CRUD for ' . $name . ' created successfully.');
	}


	protected function generateModel($name, $fields)
	{
		$tableName = Str::plural(Str::snake($name));
		$fillableFields = "'" . implode("', '", array_keys($fields)) . "'";

		$modelTemplate = str_replace(
			['{{modelName}}', '{{tableName}}', '{{fillableFields}}'],
			[$name, $tableName, $fillableFields],
			File::get(resource_path('stubs/Model.stub'))
		);

		File::put(app_path("/Models/{$name}.php"), $modelTemplate);
		$this->info("Model {$name} created successfully.");
	}


	protected function parseFields($option)
	{
		$fields = [];
		$pairs = explode(',', $option);

		foreach ($pairs as $pair) {
			[$field, $type] = explode(':', $pair);
			$fields[$field] = $type;
		}

		return $fields;
	}

	protected function generateController($name)
	{
		$modelName = ucfirst($name);
		$modelNameSnake = Str::snake($name);

		$controllerTemplate = str_replace(
			['{{ modelName }}', '{{ modelNameSnake }}'],
			[$modelName, $modelNameSnake],
			File::get(resource_path('stubs/Controller.stub'))
		);

		File::put(app_path("/Http/Controllers/{$modelName}Controller.php"), $controllerTemplate);
		$this->info("Controller {$modelName}Controller created successfully.");
	}

	protected function addRoute($name)
	{
		$modelName = Str::snake($name);
		$controllerName = $name . 'Controller'; // Nama controller
		$routePath = base_path('routes/web.php');

		// Cek apakah route ajax sudah ada
		$ajaxRoute = "Route::post('{$modelName}/ajax', [{$controllerName}::class, 'ajax'])->name('{$modelName}.ajax');";
		$resourceRoute = "Route::resource('{$modelName}', {$controllerName}::class);";

		if (File::exists($routePath)) {
			$routesContent = File::get($routePath);

			// Cek apakah route ajax sudah ada, jika belum, tambahkan
			if (!str_contains($routesContent, $ajaxRoute)) {
				File::append($routePath, "\n" . $ajaxRoute);
				$this->info("Route ajax for {$name} added successfully.");
			} else {
				$this->info("Route ajax for {$name} already exists.");
			}

			// Cek apakah route resource sudah ada, jika belum, tambahkan
			if (!str_contains($routesContent, $resourceRoute)) {
				File::append($routePath, "\n" . $resourceRoute);
				$this->info("Route resource for {$name} added successfully.");
			} else {
				$this->info("Route resource for {$name} already exists.");
			}

			// Cek dan tambahkan import controller jika belum ada
			$controllerImport = "use App\Http\Controllers\\{$controllerName};";
			if (!str_contains($routesContent, $controllerImport)) {
				// Tambahkan import controller di atas
				$routesContent = preg_replace('/^<\?php/', "<?php\n\n{$controllerImport}", $routesContent);
				File::put($routePath, $routesContent);
				$this->info("Controller import added successfully.");
			}
		} else {
			$this->warn("Route file not found.");
		}
	}


	protected function generateMigration($name, $fields)
	{
		$tableName = Str::plural(Str::snake($name));
		$migrationFileName = date('Y_m_d_His') . "_create_{$tableName}_table.php";

		$migrationFields = '';
		foreach ($fields as $field => $type) {
			$migrationFields .= "\$table->$type('$field')->nullable();\n            ";
		}

		$migrationTemplate = str_replace(
			['{{tableName}}', '{{migrationFields}}'],
			[$tableName, trim($migrationFields)],
			File::get(resource_path('stubs/Migration.stub'))
		);

		File::put(database_path("/migrations/{$migrationFileName}"), $migrationTemplate);
		$this->info("Migration for {$name} created successfully.");
	}


	protected function generateViews($name)
	{
		$viewName = Str::snake($name);
		$viewPath = resource_path("views/{$viewName}");

		if (!File::exists($viewPath)) {
			File::makeDirectory($viewPath, 0755, true);
		}

		$viewTemplates = ['index', 'modal', 'script', 'table'];
		foreach ($viewTemplates as $view) {
			$template = str_replace('{{ modelName }}', $viewName, File::get(resource_path("stubs/views/{$view}.stub")));
			File::put("{$viewPath}/{$view}.blade.php", $template);
		}

		$this->info("Views for {$name} created successfully.");
	}
}

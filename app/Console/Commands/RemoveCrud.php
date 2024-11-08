<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RemoveCrud extends Command
{
	protected $signature = 'remove:crud {name : The name of the model to remove}';
	protected $description = 'Remove CRUD files for the given model';

	public function handle()
	{
		$name = $this->argument('name');
		$modelName = ucfirst($name);
		$modelNameSnake = Str::snake($name);
		$tableName = Str::plural($modelNameSnake); // Asumsi tabel menggunakan bentuk jamak

		// Hapus file model
		$this->deleteFile(app_path("/Models/{$modelName}.php"), "Model {$modelName} deleted successfully.");

		// Hapus controller
		$this->deleteFile(app_path("/Http/Controllers/{$modelName}Controller.php"), "Controller {$modelName}Controller deleted successfully.");

		// Hapus migration
		$this->deleteMigration($tableName);

		// Hapus views
		$this->deleteDirectory(resource_path("views/{$modelNameSnake}"), "Views for {$modelNameSnake} deleted successfully.");

		// Hapus route
		$this->removeRoutes($modelNameSnake, $modelName);
	}

	private function deleteFile($path, $successMessage)
	{
		if (File::exists($path)) {
			File::delete($path);
			$this->info($successMessage);
		} else {
			$this->warn("File at {$path} not found.");
		}
	}

	private function deleteMigration($tableName)
	{
		$migrationFiles = glob(database_path("/migrations/*_create_{$tableName}_table.php"));
		if ($migrationFiles) {
			foreach ($migrationFiles as $migrationFile) {
				File::delete($migrationFile);
				$this->info("Migration {$migrationFile} deleted successfully.");
			}
		} else {
			$this->warn("No migration found for table {$tableName}.");
		}
	}

	private function deleteDirectory($path, $successMessage)
	{
		if (File::exists($path)) {
			File::deleteDirectory($path);
			$this->info($successMessage);
		} else {
			$this->warn("Directory {$path} not found.");
		}
	}

	private function removeRoutes($modelNameSnake, $modelName)
	{
		$routePath = base_path('routes/web.php');
		if (File::exists($routePath)) {
			$routesContent = File::get($routePath);

			// Hapus route ajax
			$ajaxRoutePattern = "Route::post('{$modelNameSnake}/ajax', [{$modelName}Controller::class, 'ajax'])->name('{$modelNameSnake}.ajax');";
			$routesContent = str_replace($ajaxRoutePattern . "\n", '', $routesContent);

			// Hapus route resource
			$resourceRoutePattern = "Route::resource('{$modelNameSnake}', {$modelName}Controller::class);";
			$routesContent = str_replace($resourceRoutePattern . "\n", '', $routesContent);

			// Hapus import controller jika tidak ada lagi route yang menggunakan controller ini
			$controllerImportPattern = "use App\Http\Controllers\\{$modelName}Controller;";
			if (!str_contains($routesContent, "Route::resource('{$modelNameSnake}', {$modelName}Controller::class)")) {
				$routesContent = str_replace($controllerImportPattern . "\n", '', $routesContent);
			}

			File::put($routePath, $routesContent);
			$this->info("Routes for {$modelName} removed from web.php.");
		} else {
			$this->warn("Route file not found.");
		}
	}
}

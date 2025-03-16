<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeOrionController extends Command
{
    protected $signature = 'make:orion {name}';
    protected $description = 'Generate a new Orion controller and its model if not exists';

    public function handle()
    {
        $name = str_replace('\\', '/', $this->argument('name')); // Normalize path
        $path = app_path("Http/Controllers/Orion/{$name}.php");

        // Extract directory path and class name
        $directory = dirname($path);
        $className = basename($name);
        $modelName = Str::singular(str_replace('Controller', '', class_basename($className)));


        // Ensure the directory exists
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        // Define namespace dynamically
        $namespace = "App\\Http\\Controllers\\Orion";
        $namespace = rtrim($namespace, '\\'); // Remove trailing slash if root

        // Orion controller template
        $controllerStub = <<<PHP
        <?php

        namespace {$namespace};

        use Orion\Http\Controllers\Controller;
        use App\Models\\{$modelName};

        class {$className} extends Controller
        {
            protected \$model = {$modelName}::class;
        }
        PHP;

        // Create the controller file
        if (!File::exists($path)) {
            File::put($path, $controllerStub);
            $this->info("✅ Orion controller created: app/Http/Controllers/Orion/{$name}.php");
        } else {
            $this->error("⚠️ Controller already exists!");
        }

        // **Check & Create Model if Not Exists**
        $modelPath = app_path("Models/{$modelName}.php");

        if (!File::exists($modelPath)) {
            // Model template
            $modelStub = <<<PHP
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Factories\HasFactory;
            use Illuminate\Database\Eloquent\Model;

            class {$modelName} extends Model
            {
                use HasFactory;

                protected \$guarded = [];
            }
            PHP;

            // Create the model file
            File::put($modelPath, $modelStub);
            $this->info("✅ Model created: app/Models/{$modelName}.php");
        } else {
            $this->info("✅ Model already exists: app/Models/{$modelName}.php");
        }

        // **Add Route to api.php**
        $routePath = base_path('routes/api.php');
        $routeName = Str::plural(Str::kebab($modelName)); // Plural and kebab-case
        $routeEntry = "\nOrion::resource('{$routeName}', \\{$namespace}\\{$className}::class);";

        // Ensure api.php exists and contains required imports
        if (!File::exists($routePath)) {
            File::put($routePath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\nuse Orion\\Facades\\Orion;\n\n" . $routeEntry);
        } else {
            $routes = File::get($routePath);

            // Check if the route already exists
            if (!Str::contains($routes, $routeEntry)) {
                File::append($routePath, "\n" . $routeEntry);
                $this->info("✅ Route added to api.php: Orion::resource('{$routeName}', \\{$namespace}\\{$className}::class);");
            } else {
                $this->error("⚠️ Route already exists in api.php");
            }
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenApi\Generator as OpenApiGenerator;
use Symfony\Component\Finder\Finder;

class GenerateSwaggerDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:generate-custom';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Swagger documentation ignoring PathItem warnings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating Swagger documentation...');
        
        // Suprimir warnings temporalmente
        $oldErrorReporting = error_reporting();
        error_reporting(E_ALL & ~E_USER_WARNING & ~E_USER_NOTICE);
        
        try {
            // Crear generador
            $generator = new OpenApiGenerator();
            
            // Crear finder para escanear archivos PHP
            $finder = Finder::create()
                ->files()
                ->name('*.php')
                ->in(base_path('app'));
            
            // Generar documentación
            $openapi = $generator->generate($finder);
            
            // Crear directorio si no existe
            $storagePath = storage_path('api-docs');
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }
            
            // Guardar como JSON
            file_put_contents(
                $storagePath . '/api-docs.json',
                $openapi->toJson()
            );
            
            // Guardar como YAML
            file_put_contents(
                $storagePath . '/api-docs.yaml',
                $openapi->toYaml()
            );
            
            $this->info('✓ Swagger documentation generated successfully!');
            $this->info('  JSON: storage/api-docs/api-docs.json');
            $this->info('  YAML: storage/api-docs/api-docs.yaml');
            $this->info('  View at: ' . url('/api/documentation'));
            
        } catch (\Exception $e) {
            $this->error('Error generating documentation: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            error_reporting($oldErrorReporting);
            return 1;
        }
        
        // Restaurar error reporting
        error_reporting($oldErrorReporting);
        
        return 0;
    }
}

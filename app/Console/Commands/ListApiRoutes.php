<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class ListApiRoutes extends Command
{
    protected $signature = 'route:api';
    protected $description = 'List all API routes';

    public function handle()
    {
        $routes = Route::getRoutes();
        $this->info("API Routes:");
        $this->info("===========");
        
        foreach ($routes as $route) {
            if (strpos($route->uri, 'api/') === 0) {
                $this->info($route->methods[0] . ' - ' . $route->uri . ' - ' . $route->getActionName());
            }
        }
        
        return Command::SUCCESS;
    }
} 
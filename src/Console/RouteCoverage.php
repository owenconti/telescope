<?php

namespace Laravel\Telescope\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

class RouteCoverage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:coverage {minutes} {prefix?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Outputs the coverage of the application\'s routes.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $minutes = $this->argument('minutes');
        $prefix = $this->argument('prefix');

        $telescopeEntries = DB::connection(config('telescope.storage.database.connection'))
            ->table('telescope_entries')
            ->where('type', 'request')
            ->where('created_at', '>', Carbon::now()->subMinutes($minutes))
            ->get();

        $registeredRoutes = collect(Route::getRoutes()->get())
            ->filter(function ($route) use ($prefix) {
                if (isset($prefix) && !empty($prefix)) {
                    return strpos($route->uri, $prefix) === 0;
                }

                return true;
            });

        $requests = $telescopeEntries->filter(function ($entry) use ($prefix) {
            $content = json_decode($entry->content);

            if (isset($prefix) && !empty($prefix)) {
                return strpos($content->uri, $prefix) === 0;
            }

            return true;
        })->map(function ($entry) {
            $content = json_decode($entry->content);
            return isset($content->controller_action) ? $content->controller_action : $content->uri;
        });

        $unconveredRoutes = $registeredRoutes->filter(function ($route) use ($requests) {
            $routeIdentifier = $this->getRouteIdentifier($route);

            return !$requests->contains($routeIdentifier);
        });

        if ($unconveredRoutes->isNotEmpty()) {
            $this->info('The following routes were not covered:');

            $unconveredRoutes->each(function ($route) {
                $this->info($route->uri);
            });
        }

        $this->info('Total routes registered: ' . $registeredRoutes->count());
        $this->info('Unique requests made in the last ' . $minutes . ' minutes: ' . $requests->count());
        $this->info('Percentage of requests hit: ' . round(($requests->count() / $registeredRoutes->count()) * 100) . '%');
    }

    private function getRouteIdentifier($route)
    {
        if (isset($route->action['controller'])) {
            return $route->action['controller'];
        }

        return $route->uri;
    }
}

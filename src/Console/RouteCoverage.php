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

        $requests = $telescopeEntries->map(function ($entry) {
            $content = json_decode($entry->content);

            return $content->controller_action !== 'Closure' ? $content->controller_action : $content->uri;
        });

        $coverage = $registeredRoutes->reduce(function($carry, $route) use ($requests) {
            $routeIdentifier = $this->getRouteIdentifier($route);

            $type = 'covered';
            if (!$requests->contains($routeIdentifier)) {
                $type = 'uncovered';
            }

            $carry[$type]->push($route->uri);

            return $carry;
        }, [
            'covered' => collect([]),
            'uncovered' => collect([])
        ]);

        if ($coverage['uncovered']->isNotEmpty()) {
            $this->info('The following routes were not covered:');

            $coverage['uncovered']->each(function ($routeUri) {
                $this->line($routeUri);
            });
            $this->line('');
        }

        $this->info('Total routes registered: ' . $registeredRoutes->count());
        $this->info('Unique requests made in the last ' . $minutes . ' minutes: ' . $coverage['covered']->count());
        $this->info('Percentage of requests hit: ' . round(($coverage['covered']->count() / $registeredRoutes->count()) * 100) . '%');
    }

    private function getRouteIdentifier($route)
    {
        if (isset($route->action['controller'])) {
            return $route->action['controller'];
        }

        return '/' . $route->uri;
    }
}

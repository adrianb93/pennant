<?php

namespace Laravel\Pennant\Concerns;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\ForwardsCalls;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PendingScopedFeatureInteraction;

trait InteractsWithFeature
{
    use ForwardsCalls;

    /**
     * Decorated feature interaction instance.
     */
    private PendingScopedFeatureInteraction $instance;

    /**
     * Resolve the feature's initial value.
     */
    public function resolve($scope): bool
    {
        $resolver = $scope ? $scope::class : 'null';
        $subscribed = Config::get('pennant.subscribe', []);

        $handle = value($subscribed[$resolver] ?? null, $scope);

        return $handle && method_exists($this, $handle)
            ? (bool) $this->{$handle}($scope)
            : true;
    }

    /**
     * Handle dynamic static method calls into the feature interactions.
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return Application::make(static::class)->{$method}(...$parameters);
    }

    /**
     * Handle dynamic method calls into the feature interactions and provide the
     * feature parameter given we are interacting from said feature.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        $this->instance ??= Feature::for([]);

        $needsFeatureParameter = in_array($method, [
            'activate', 'active', 'allAreActive', 'allAreInactive', 'can', 'cant',
            'canAny', 'cannot', 'deactivate', 'forget', 'inactive', 'when', 'unless',
            'value', 'values', 'load', 'loadMissing', 'someAreActive', 'someAreInactive',
        ]);

        if ($needsFeatureParameter) {
            $parameters = [$this::class, ...$parameters];
        }

        return $this->forwardDecoratedCallTo($this->instance, $method, $parameters);
    }
}

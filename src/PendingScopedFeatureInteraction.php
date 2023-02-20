<?php

namespace Laravel\Pennant;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class PendingScopedFeatureInteraction
{
    /**
     * The feature driver.
     *
     * @var \Laravel\Pennant\Drivers\Decorator
     */
    protected $driver;

    /**
     * The feature interaction scope.
     *
     * @var array<mixed>
     */
    protected $scope = [];

    /**
     * Create a new Pending Scoped Feature Interaction instance.
     *
     * @param  \Laravel\Pennant\Drivers\Decorator  $driver
     */
    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    /**
     * Determine if the entity has the ability for the given flagged features.
     *
     * @param  array|mixed  $parameters
     */
    public function can(iterable|string $features, $parameters = []): bool
    {
        $user = $this->findScoped(User::class);

        return $this->active($features)
            && Gate::forUser($user)->check($this->gatedFeatures($features), $parameters);
    }

    /**
     * Determine if the entity has any abilities for the given flagged features.
     *
     * @param  array|mixed  $parameters
     */
    public function canAny(iterable|string $features, $parameters = []): bool
    {
        return (bool) Collection::wrap($features)->first(fn ($feature) => $this->can($feature, $parameters));
    }

    /**
     * Determine if the entity does not have the ability for the given flagged features.
     *
     * @param  array|mixed  $parameters
     */
    public function cant(iterable|string $features, $parameters = []): bool
    {
        return ! $this->can($features, $parameters);
    }

    /**
     * Determine if the entity does not have the ability for the given flagged features.
     *
     * @param  array|mixed  $parameters
     */
    public function cannot(iterable|string $features, $parameters = []): bool
    {
        return $this->cant($features, $parameters);
    }

    /**
     * Add scope to the feature interaction.
     *
     * @param  mixed  $scope
     * @return $this
     */
    public function for($scope)
    {
        $this->scope = array_merge($this->scope, Collection::wrap($scope)->all());

        return $this;
    }

    /**
     * Load the feature into memory.
     *
     * @param  string|array<int, string>  $features
     * @return array<string, array<int, mixed>>
     */
    public function load($features)
    {
        return Collection::wrap($features)
            ->mapWithKeys(fn ($feature) => [$feature => $this->scope()])
            ->pipe(fn ($features) => $this->driver->getAll($features->all()));
    }

    /**
     * Load the missing features into memory.
     *
     * @param  string|array<int, string>  $features
     * @return array<string, array<int, mixed>>
     */
    public function loadMissing($features)
    {
        return Collection::wrap($features)
            ->mapWithKeys(fn ($feature) => [$feature => $this->scope()])
            ->pipe(fn ($features) => $this->driver->getAllMissing($features->all()));
    }

    /**
     * Get the value of the flag.
     *
     * @param  string  $feature
     * @return mixed
     */
    public function value($feature)
    {
        return $this->values([$feature])[$feature];
    }

    /**
     * Get the values of the flag.
     *
     * @param  array<string>  $features
     * @return array<string, mixed>
     */
    public function values($features)
    {
        if (count($this->scope()) > 1) {
            throw new RuntimeException('It is not possible to retrieve the values for mutliple scopes.');
        }

        $this->loadMissing($features);

        return Collection::make($features)
            ->mapWithKeys(fn ($feature) => [
                $feature => $this->driver->get($feature, $this->scope()[0]),
            ])
            ->all();
    }

    /**
     * Retrieve all the features and their values.
     *
     * @return array<string, mixed>
     */
    public function all()
    {
        return $this->values($this->driver->defined());
    }

    /**
     * Determine if the feature is active.
     *
     * @param  string  $feature
     * @return bool
     */
    public function active($feature)
    {
        return $this->allAreActive([$feature]);
    }

    /**
     * Determine if all the features are active.
     *
     * @param  array<string>  $features
     * @return bool
     */
    public function allAreActive($features)
    {
        $this->loadMissing($features);

        return Collection::make($features)
            ->crossJoin($this->scope())
            ->every(fn ($bits) => $this->driver->get(...$bits) !== false);
    }

    /**
     * Determine if any of the features are active.
     *
     * @param  array<string>  $features
     * @return bool
     */
    public function someAreActive($features)
    {
        $this->loadMissing($features);

        return Collection::make($this->scope())
            ->every(fn ($scope) => Collection::make($features)
                ->some(fn ($feature) => $this->driver->get($feature, $scope) !== false));
    }

    /**
     * Determine if the feature is inactive.
     *
     * @param  string  $feature
     * @return bool
     */
    public function inactive($feature)
    {
        return $this->allAreInactive([$feature]);
    }

    /**
     * Determine if all the features are inactive.
     *
     * @param  array<string>  $features
     * @return bool
     */
    public function allAreInactive($features)
    {
        $this->loadMissing($features);

        return Collection::make($features)
            ->crossJoin($this->scope())
            ->every(fn ($bits) => $this->driver->get(...$bits) === false);
    }

    /**
     * Determine if any of the features are inactive.
     *
     * @param  array<string>  $features
     * @return bool
     */
    public function someAreInactive($features)
    {
        $this->loadMissing($features);

        return Collection::make($this->scope())
            ->every(fn ($scope) => Collection::make($features)
                ->some(fn ($feature) => $this->driver->get($feature, $scope) === false));
    }

    /**
     * Apply the callback if the feature is active.
     *
     * @param  string  $feature
     * @param  \Closure  $whenActive
     * @param  \Closure|null  $whenInactive
     * @return mixed
     */
    public function when($feature, $whenActive, $whenInactive = null)
    {
        if ($this->active($feature)) {
            return $whenActive($this->value($feature), $this);
        }

        return $whenInactive($this);
    }

    /**
     * Apply the callback if the feature is inactive.
     *
     * @param  string  $feature
     * @param  \Closure  $whenInactive
     * @param  \Closure|null  $whenActive
     * @return mixed
     */
    public function unless($feature, $whenInactive, $whenActive = null)
    {
        return $this->when($feature, $whenActive ?? fn () => null, $whenInactive);
    }

    /**
     * Activate the feature.
     *
     * @param  string|array<string>  $feature
     * @param  mixed  $value
     * @return void
     */
    public function activate($feature, $value = true)
    {
        Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->each(fn ($bits) => $this->driver->set($bits[0], $bits[1], $value));
    }

    /**
     * Deactivate the feature.
     *
     * @param  string|array<string>  $feature
     * @return void
     */
    public function deactivate($feature)
    {
        Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->each(fn ($bits) => $this->driver->set($bits[0], $bits[1], false));
    }

    /**
     * Forget the flags value.
     *
     * @param  string|array<string>  $features
     * @return void
     */
    public function forget($features)
    {
        Collection::wrap($features)
            ->crossJoin($this->scope())
            ->each(fn ($bits) => $this->driver->delete(...$bits));
    }

    /**
     * The scope to pass to the driver.
     *
     * @return array<mixed>
     */
    protected function scope()
    {
        return $this->scope ?: [null];
    }

    /**
     * Find a scoped instance.
     *
     * @return null|mixed
     */
    protected function findScoped(string $class)
    {
        return Collection::make($this->scope())->first(
            fn ($scope) => $scope && $scope instanceof $class
        );
    }

    /**
     * Returns filtered array of class based features with a gate function.
     */
    protected function gatedFeatures(iterable|string $features): array
    {
        $user = $this->findScoped(User::class);
        $subscribed = Config::get('pennant.subscribe', []);
        $handleUser = value(collect($subscribed)->keys()->first(fn ($subscribes) => $user instanceof $subscribes), $user);

        return Collection::wrap($features)
            ->filter(fn ($feature) => class_exists($feature))
            ->filter(fn ($feature) => match (true) {
                method_exists($feature, 'gate') => Gate::define($feature, [$feature, 'gate']),
                $handleUser && method_exists($feature, $handleUser) => Gate::define($feature, [$feature, $handleUser]),
                default => false,
            })
            ->values()
            ->all();
    }
}

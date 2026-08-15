<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every factory must actually persist.
 *
 * `make()` proves nothing here — it never touches the database, so a NOT NULL column
 * or a wrong foreign key name passes it and fails only when someone writes a test
 * months later. These all go through `create()` on purpose.
 */
class FactoryCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    protected function modelsWithFactories(): array
    {
        return collect(glob(database_path('factories/*Factory.php')))
            ->map(fn (string $path): string => 'App\\Models\\'.Str::before(basename($path, '.php'), 'Factory'))
            ->filter(fn (string $class): bool => class_exists($class))
            ->values()
            ->all();
    }

    public function test_every_factory_can_persist_its_model(): void
    {
        $failures = [];

        foreach ($this->modelsWithFactories() as $class) {
            try {
                /** @var Model $model */
                $model = $class::factory()->create();

                $this->assertTrue(
                    $model->exists,
                    "{$class} factory returned a model that was never saved."
                );
            } catch (\Throwable $e) {
                $failures[] = class_basename($class).': '.$e->getMessage();
            }
        }

        $this->assertSame([], $failures, "Factories that cannot persist:\n".implode("\n", $failures));
    }

    public function test_every_declared_state_produces_a_valid_record(): void
    {
        $failures = [];

        foreach ($this->modelsWithFactories() as $class) {
            $factory = $class::factory();

            // Public methods the factory adds on top of the base class are its states.
            $states = array_diff(
                get_class_methods($factory),
                get_class_methods(Factory::class),
                ['definition', 'modelName', 'newModel']
            );

            foreach ($states as $state) {
                try {
                    $reflection = new \ReflectionMethod($factory, $state);

                    // States taking arguments need context this sweep cannot invent.
                    if ($reflection->getNumberOfRequiredParameters() > 0) {
                        continue;
                    }

                    $this->assertTrue($factory->{$state}()->create()->exists);
                } catch (\Throwable $e) {
                    $failures[] = class_basename($class)."::{$state}(): ".$e->getMessage();
                }
            }
        }

        $this->assertSame([], $failures, "Factory states that fail:\n".implode("\n", $failures));
    }

    public function test_factories_that_declare_unique_columns_can_make_several_at_once(): void
    {
        // The reason UserFactory alone was not enough: tests routinely need three
        // contacts or two categories, and a non-unique factory fails only then.
        $failures = [];

        foreach ($this->modelsWithFactories() as $class) {
            try {
                $class::factory()->count(3)->create();
            } catch (\Throwable $e) {
                $failures[] = class_basename($class).': '.$e->getMessage();
            }
        }

        $this->assertSame([], $failures, "Factories that collide on a second row:\n".implode("\n", $failures));
    }
}

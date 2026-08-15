<?php

namespace Tests\Feature\Policies;

use App\Policies\BasePolicy;
use App\Policies\RolePolicy;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The guard that keeps this layer from rotting.
 *
 * Adding a model with an admin screen but no policy is silent: Gate::allows() denies,
 * the `can` flags come back false, and the buttons vanish with no error anywhere.
 * This test turns that into a failing build.
 */
class PolicyCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Models intentionally outside the policy layer, with the reason.
     *
     * @var array<string, string>
     */
    protected const NOT_POLICIED = [
        'AppNotification' => 'own notifications, scoped by owner in NotificationCenter',
        'NotificationPreference' => 'own preferences, never listed for other users',
        'BackupLog' => 'read-only child rows rendered inside the backup screen',
        'MediaVariant' => 'child rows of Media, governed by MediaPolicy',
        'WhatsAppBroadcastRecipient' => 'child rows of WhatsAppBroadcast',
        'WhatsAppContact' => 'no admin screen of its own',
        'WhatsAppWebhookEvent' => 'read-only observability screen, gated by permission',
        'SocialLink' => 'edited inline within site settings',
        'SystemSetting' => 'key/value store written through services only',
        'OtpCode' => 'short-lived credentials read only by OtpService — never listed or edited',
        'User' => 'has UserPolicy',   // present, but listed so the map stays explicit
    ];

    /**
     * @return array<int, string>
     */
    protected function modelClasses(): array
    {
        return collect(glob(app_path('Models/*.php')))
            ->map(fn (string $path): string => 'App\\Models\\'.basename($path, '.php'))
            ->filter(fn (string $class): bool => class_exists($class))
            ->values()
            ->all();
    }

    public function test_every_model_either_has_a_policy_or_a_documented_exemption(): void
    {
        $missing = [];

        foreach ($this->modelClasses() as $model) {
            $short = class_basename($model);

            if (array_key_exists($short, self::NOT_POLICIED)) {
                continue;
            }

            if (! class_exists('App\\Policies\\'.$short.'Policy')) {
                $missing[] = $short;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "These models have no policy and no documented exemption: \n  ".implode("\n  ", $missing)
                ."\nAdd App\\Policies\\{Model}Policy extending BasePolicy, or add the model to "
                .'PolicyCoverageTest::NOT_POLICIED with a reason.'
        );
    }

    public function test_gate_resolves_a_policy_for_every_policied_model(): void
    {
        foreach ($this->modelClasses() as $model) {
            $short = class_basename($model);
            $policy = 'App\\Policies\\'.$short.'Policy';

            if (! class_exists($policy)) {
                continue;
            }

            $this->assertNotNull(
                Gate::getPolicyFor($model),
                "Gate could not resolve a policy for [{$model}] even though [{$policy}] exists."
            );
        }
    }

    public function test_the_spatie_role_policy_is_registered_explicitly(): void
    {
        // Role lives in Spatie\Permission\Models, so convention-based discovery
        // cannot find App\Policies\RolePolicy — it must be bound by hand.
        $this->assertInstanceOf(
            RolePolicy::class,
            Gate::getPolicyFor(config('permission.models.role'))
        );
    }

    public function test_every_policy_points_at_a_resource_that_exists_in_the_registry(): void
    {
        $known = collect(Permissions::all())
            ->map(fn (string $name): string => Str::beforeLast($name, '.'))
            ->unique()
            ->values()
            ->all();

        $orphans = [];

        foreach (glob(app_path('Policies/*.php')) as $path) {
            $class = 'App\\Policies\\'.basename($path, '.php');

            if (! class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $policy = new $class;

            if (! $policy instanceof BasePolicy) {
                continue;
            }

            if (! in_array('admin.'.$policy->resourceKey(), $known, true)) {
                $orphans[] = class_basename($class).' → '.$policy->resourceKey();
            }
        }

        $this->assertSame(
            [],
            $orphans,
            'These policies reference a resource with no permissions in config/permissions.php: '
                .implode(', ', $orphans)
        );
    }

    public function test_every_ownable_policy_has_its_any_permissions_registered(): void
    {
        $known = Permissions::all();
        $missing = [];

        foreach (glob(app_path('Policies/*.php')) as $path) {
            $class = 'App\\Policies\\'.basename($path, '.php');

            if (! class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $policy = new $class;

            if (! $policy instanceof BasePolicy || ! $policy->isOwnable()) {
                continue;
            }

            $crud = Permissions::crud('admin', $policy->resourceKey());

            foreach (['editAny', 'deleteAny'] as $key) {
                if (! in_array($crud[$key], $known, true)) {
                    $missing[] = $crud[$key];
                }
            }
        }

        // An ownable policy without its -any grants can never delegate — the owner
        // rule would be permanent with no way to authorise an editor.
        $this->assertSame([], $missing, 'Missing -any permissions: '.implode(', ', $missing));
    }
}

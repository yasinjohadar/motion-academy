<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Concerns\ResolvesImageField;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamMemberRequest;
use App\Http\Requests\Admin\TeamSettingsRequest;
use App\Models\TeamMember;
use App\Services\Frontend\TeamSettings;
use App\Services\Storage\AppStorageManager;
use App\Support\SocialPlatforms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The expert team and how the home page section presents it.
 */
class TeamMemberController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource, ResolvesImageField;

    public function __construct(
        private readonly AppStorageManager $storageManager,
        private readonly TeamSettings $settings,
    ) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'team-members',
            false,
            [
                'edit' => ['toggleActive', 'updateSettings'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('query'));
        $status = $request->string('status')->toString();

        $members = TeamMember::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('title', 'like', "%{$query}%")
                        ->orWhere('bio', 'like', "%{$query}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (TeamMember $member) => $member->toAdminArray());

        return Inertia::render('Admin/TeamMembers/Index', [
            'members' => $members,
            'counts' => [
                'all' => TeamMember::query()->count(),
                'active' => TeamMember::query()->active()->count(),
            ],
            'settings' => $this->settings->all(),
            'options' => $this->settingsOptions(),
            'platforms' => SocialPlatforms::options(),
            'filters' => [
                'query' => $query,
                'status' => $status,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TeamMembers/Create', [
            'platforms' => SocialPlatforms::options(),
            'nextSortOrder' => (int) TeamMember::query()->max('sort_order') + 1,
        ]);
    }

    public function store(TeamMemberRequest $request): RedirectResponse
    {
        $avatar = $this->resolveImageField($request, 'avatar', TeamMember::IMAGE_DISK, 'team');

        TeamMember::create([
            ...$this->attributes($request),
            'avatar' => $avatar ?: null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.team-members.index')
            ->with('success', 'تمت إضافة العضو بنجاح.');
    }

    public function edit(TeamMember $team_member): Response
    {
        $this->authorize('update', $team_member);

        return Inertia::render('Admin/TeamMembers/Edit', [
            'member' => $team_member->toAdminArray(),
            'platforms' => SocialPlatforms::options(),
        ]);
    }

    public function update(TeamMemberRequest $request, TeamMember $team_member): RedirectResponse
    {
        $this->authorize('update', $team_member);

        $avatar = $this->resolveImageField(
            $request, 'avatar', TeamMember::IMAGE_DISK, 'team',
            $team_member->hasUploadedAvatar() ? $team_member->avatar : null,
        );

        $team_member->update([
            ...$this->attributes($request),
            ...($avatar !== false ? ['avatar' => $avatar] : []),
        ]);

        return redirect()
            ->route('admin.team-members.index')
            ->with('success', 'تم تحديث بيانات العضو بنجاح.');
    }

    public function destroy(TeamMember $team_member): RedirectResponse
    {
        $this->authorize('delete', $team_member);

        if ($team_member->hasUploadedAvatar()) {
            $this->storageManager->delete(TeamMember::IMAGE_DISK, $team_member->avatar);
        }

        $team_member->delete();

        return back()->with('success', 'تم حذف العضو.');
    }

    public function toggleActive(TeamMember $team_member): RedirectResponse
    {
        $this->authorize('update', $team_member);

        $team_member->update(['is_active' => ! $team_member->is_active]);

        return back()->with('success', $team_member->is_active ? 'تم إظهار العضو.' : 'تم إخفاء العضو.');
    }

    public function updateSettings(TeamSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->settings());

        return back()->with('success', 'تم حفظ إعدادات قسم الفريق.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(TeamMemberRequest $request): array
    {
        $data = $request->validated();

        return [
            'name' => $data['name'],
            'title' => $data['title'] ?? null,
            'bio' => $data['bio'] ?? null,
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'rating' => $data['rating'] ?? null,
            'profile_url' => $data['profile_url'] ?? null,
            'social_links' => array_values(array_map(
                fn (array $link) => ['platform' => $link['platform'], 'url' => $link['url']],
                $data['social_links'] ?? [],
            )),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * @return array<string, array<int, array{value: int|string, label: string}>>
     */
    private function settingsOptions(): array
    {
        $pairs = fn (array $options) => array_map(
            fn (string $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys($options),
            $options,
        );

        return [
            'orders' => $pairs(TeamSettings::ORDERS),
            'layouts' => $pairs(TeamSettings::LAYOUTS),
            'columns' => array_map(fn (int $count) => ['value' => $count, 'label' => (string) $count], TeamSettings::COLUMNS),
        ];
    }
}

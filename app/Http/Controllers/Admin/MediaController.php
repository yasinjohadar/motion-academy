<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaStoreRequest;
use App\Http\Requests\Admin\MediaUpdateRequest;
use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'media',
            true,
            [
                'admin.media.create' => ['upload'],
                'admin.media.list' => ['picker'],
            ],
            ['index', 'picker'],
        );
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Media/Index', [
            'media' => $this->paginateMedia($request),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'mime' => $request->string('mime')->toString(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function picker(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->paginateMedia($request),
        ]);
    }

    public function store(MediaStoreRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $media = $this->mediaService->upload(
                $request->file('file'),
                $request->user(),
                $request->validated('title'),
                $request->validated('alt'),
            );
        } catch (\Throwable $e) {
            if ($this->wantsPlainJson($request)) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $payload = $this->mediaService->toAdminArray($media);

        if ($this->wantsPlainJson($request)) {
            return response()->json([
                'message' => 'تم رفع الصورة ومعالجتها.',
                'media' => $payload,
                'location' => $payload['url'],
            ]);
        }

        return back()->with('success', 'تم رفع الصورة ومعالجتها.');
    }

    /**
     * TinyMCE / external upload endpoint (JSON only).
     */
    public function upload(MediaStoreRequest $request): JsonResponse
    {
        try {
            $media = $this->mediaService->upload(
                $request->file('file'),
                $request->user(),
                $request->validated('title'),
                $request->validated('alt'),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => $e->getMessage()], 422);
        }

        $payload = $this->mediaService->toAdminArray($media);

        return response()->json([
            'location' => $payload['url'],
            'media' => $payload,
        ]);
    }

    public function show(Media $medium): JsonResponse
    {
        return response()->json([
            'media' => $this->mediaService->toAdminArray($medium),
        ]);
    }

    public function update(MediaUpdateRequest $request, Media $medium): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $medium);

        $media = $this->mediaService->updateMeta($medium, $request->validated());
        $payload = $this->mediaService->toAdminArray($media);

        if ($this->wantsPlainJson($request)) {
            return response()->json([
                'message' => 'تم تحديث بيانات الوسائط.',
                'media' => $payload,
            ]);
        }

        return back()->with('success', 'تم تحديث بيانات الوسائط.');
    }

    public function destroy(Request $request, Media $medium): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $medium);

        $this->mediaService->delete($medium);

        if ($this->wantsPlainJson($request)) {
            return response()->json(['message' => 'تم حذف الملف.']);
        }

        return back()->with('success', 'تم حذف الملف.');
    }

    /**
     * JSON for MediaPicker/fetch only — never for Inertia visits.
     */
    private function wantsPlainJson(Request $request): bool
    {
        if ($request->header('X-Inertia')) {
            return false;
        }

        return $request->expectsJson() || $request->wantsJson();
    }

    private function paginateMedia(Request $request): array
    {
        $query = Media::query()
            ->with(['variants', 'uploader'])
            ->latest('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('filename', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('alt', 'like', "%{$search}%");
            });
        }

        if ($mime = $request->string('mime')->trim()->toString()) {
            if ($mime === 'image') {
                $query->where('mime', 'like', 'image/%');
            } else {
                $query->where('mime', $mime);
            }
        }

        $paginator = $query->paginate(24)->withQueryString();

        return [
            'data' => $paginator->getCollection()
                ->map(fn (Media $media) => $this->mediaService->toAdminArray($media))
                ->values()
                ->all(),
            'links' => $paginator->linkCollection()->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}

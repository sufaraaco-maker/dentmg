<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\NotificationIndexRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use App\Policies\NotificationPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 5 (Notification System) design doc §7.
 *
 * **Structurally IDOR-proof.** Every action below starts from `$request->user()->notifications()`.
 * There is no route-model binding on `{notification}` and no global `Notification::find()` — a
 * notification belonging to another user is not "found and then rejected", it is never in the
 * result set at all, so these routes 404 rather than 403. This is the same structural guarantee
 * ProfileController relies on (every route resolves its target from `$request->user()`), and it is
 * why Decision D6 adds no permission catalog entry: there is nothing left to authorize.
 *
 * Layered on top, the read-time category re-check (design doc §8.2, authorization layer 2) is
 * applied to **every** query here, including the count — a badge that counted rows the user is no
 * longer allowed to open would leak the fact that they exist.
 */
class NotificationController extends Controller
{
    public function index(NotificationIndexRequest $request)
    {
        $validated = $request->validated();

        $query = $this->scopedQuery($request->user());

        match ($validated['status'] ?? 'all') {
            'unread' => $query->unread(),
            'read' => $query->read(),
            default => $query,
        };

        if (isset($validated['category'])) {
            $this->restrictToCategory($query, $request->user(), $validated['category']);
        }

        return NotificationResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 15))
        );
    }

    /**
     * Deliberately a separate endpoint from index() (design doc §7): the frontend polls this every
     * 60 seconds per open session, and it must not deserialize a page of rows just to render a
     * badge. One indexed COUNT(*), served by the partial `notifications_unread_idx` (Decision D2).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->scopedQuery($request->user())->unread()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        // findOrFail against the *user's own* scoped query — someone else's notification 404s
        // because it was never in scope, not because a check rejected it.
        /** @var Notification $model */
        $model = $this->scopedQuery($request->user())->findOrFail($notification);

        // Idempotent: Laravel's markAsRead() guards on read_at, so a double-click is harmless.
        $model->markAsRead();

        return response()->json(new NotificationResource($model->refresh()));
    }

    /**
     * Honours the caller's active category filter (design doc §7) — "mark all as read" while the
     * UI is filtered to Laboratory must not silently clear Billing too.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $query = $this->scopedQuery($request->user())->unread();

        $category = $request->input('category');
        if (is_string($category) && $category !== '') {
            $this->restrictToCategory($query, $request->user(), $category);
        }

        // reorder() strips the `latest()` ordering the notifications() relation applies. An
        // ORDER BY is meaningless on an UPDATE and is not portable across every driver this
        // project targets (Postgres in production, SQLite under test).
        $updated = $query->reorder()->update(['read_at' => now()]);

        return response()->json(['marked' => $updated]);
    }

    /**
     * The one place ownership (layer 1) and the category re-check (layer 2) are combined, so no
     * action above can apply one without the other.
     *
     * @return Builder<Notification>
     */
    private function scopedQuery(Authenticatable $user): Builder
    {
        /** @var User $user */
        return $user->notifications()
            ->getQuery()
            ->inCategories(NotificationPolicy::allowedCategories($user));
    }

    /**
     * Intersected against the allowed set, never trusted on its own — asking for a category you
     * cannot read must return nothing, not everything.
     *
     * @param  Builder<Notification>  $query
     */
    private function restrictToCategory(Builder $query, Authenticatable $user, string $category): void
    {
        /** @var User $user */
        $query->inCategories(array_values(array_intersect(
            NotificationPolicy::allowedCategories($user),
            [$category],
        )));
    }
}

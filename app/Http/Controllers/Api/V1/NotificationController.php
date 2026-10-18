<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationPrefs;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** The signed-in user's own notifications and their delivery preferences. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'between:1,50'], 'unread' => ['nullable', 'boolean']]);

        $user = $request->user();
        $page = $user->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $page->getCollection()->map(fn (DatabaseNotification $n) => $this->shape($n))->values(),
            'unread_count' => $user->unreadNotifications()->count(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function read(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['unread_count' => 0]);
    }

    public function preferences(Request $request)
    {
        return response()->json(['data' => NotificationPrefs::for($request->user())]);
    }

    public function updatePreferences(Request $request)
    {
        $rules = [];
        foreach (array_keys(NotificationPrefs::CATEGORIES) as $category) {
            $rules[$category] = ['array'];
            $rules[$category.'.app'] = ['boolean'];
            $rules[$category.'.email'] = ['boolean'];
        }

        return response()->json(['data' => NotificationPrefs::save($request->user(), $request->validate($rules))]);
    }

    private function shape(DatabaseNotification $n): array
    {
        $d = $n->data;

        return [
            'id' => $n->id,
            'category' => $d['category'] ?? null,
            'title' => $d['title'] ?? '',
            'body' => $d['body'] ?? '',
            'url' => $d['url'] ?? null,
            'level' => $d['level'] ?? 'info',
            'read_at' => $n->read_at,
            'created_at' => $n->created_at,
        ];
    }
}
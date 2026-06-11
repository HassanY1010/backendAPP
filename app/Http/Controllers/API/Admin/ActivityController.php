<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppReview;
use App\Models\Message;
use App\Models\Notification;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function appReviews(Request $request)
    {
        $query = AppReview::with('user:id,name,phone')
            ->latest();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function deleteAppReview($id)
    {
        AppReview::findOrFail($id)->delete();

        return response()->json(['message' => 'App review deleted successfully']);
    }

    public function messages(Request $request)
    {
        $query = Message::with([
            'sender:id,name,phone',
            'receiver:id,name,phone',
        ])->latest();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhereHas('sender', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('receiver', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('message_type')) {
            $query->where('message_type', $request->message_type);
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function deleteMessage($id)
    {
        Message::findOrFail($id)->delete();

        return response()->json(['message' => 'Message deleted successfully']);
    }

    public function notifications(Request $request)
    {
        $query = Notification::with('user:id,name,phone')->latest();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function deleteNotification($id)
    {
        Notification::findOrFail($id)->delete();

        return response()->json(['message' => 'Notification deleted successfully']);
    }

    public function savedSearches(Request $request)
    {
        $query = SavedSearch::with('user:id,name,phone')->latest();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('notify_enabled')) {
            $query->where('notify_enabled', $request->boolean('notify_enabled'));
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function updateSavedSearch(Request $request, $id)
    {
        $request->validate([
            'notify_enabled' => 'required|boolean',
        ]);

        $savedSearch = SavedSearch::findOrFail($id);
        $savedSearch->update([
            'notify_enabled' => $request->boolean('notify_enabled'),
        ]);

        return response()->json(['message' => 'Saved search updated', 'data' => $savedSearch]);
    }

    public function deleteSavedSearch($id)
    {
        SavedSearch::findOrFail($id)->delete();

        return response()->json(['message' => 'Saved search deleted successfully']);
    }

    public function phoneVerifications(Request $request)
    {
        $query = User::query()
            ->select([
                'id',
                'name',
                'phone',
                'role',
                'is_active',
                'phone_verified_at',
                'created_at',
            ])
            ->latest();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('verified')) {
            $request->boolean('verified')
                ? $query->whereNotNull('phone_verified_at')
                : $query->whereNull('phone_verified_at');
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function updatePhoneVerification(Request $request, $id)
    {
        $request->validate([
            'verified' => 'required|boolean',
        ]);

        $user = User::findOrFail($id);
        $user->phone_verified_at = $request->boolean('verified') ? now() : null;
        $user->save();

        return response()->json([
            'message' => 'Phone verification updated',
            'data' => $user->only(['id', 'name', 'phone', 'phone_verified_at']),
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->get('per_page', 20), 1), 100);
    }
}

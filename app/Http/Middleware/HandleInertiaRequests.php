<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'subscription' => $user ? [
                'is_subscribed' => $user->firm?->subscribed('default') ?? false,
                'file_count' => $user->firm?->fileCount() ?? 0,
                'file_limit' => $user->firm?->fileLimit(),
                'can_create_files' => $user->firm?->canCreateFiles() ?? false,
            ] : null,
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
            ],
        ];
    }
}

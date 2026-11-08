<?php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
             ...parent::share($request),
            'name'        => config('app.name'),
         'auth' => [
            'user' => $request->user(),
            // Super Admin gets every permission that exists, including ones added later.
            'permissions' => fn () => $request->user()
                ? ($request->user()->hasRole('Super Admin')
                    ? \Spatie\Permission\Models\Permission::pluck('name')->values()
                    : $request->user()->getAllPermissions()->pluck('name')->values())
                : [],
        ],

        'flash' => [
            'success' => fn () => $request->session()->get('success'),
        ],

     

      

        ];
    }
}

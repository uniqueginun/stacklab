<?php

namespace App\Http\Controllers;

use App\Actions\Sites\CreateSite;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Resources\ReleaseResource;
use App\Http\Resources\SiteResource;
use App\Models\Operation;
use App\Models\Release;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Site::class);

        $sites = $request->user()
            ->sites()
            ->with(['server', 'currentRelease'])
            ->latest()
            ->get();

        return Inertia::render('sites/Index', [
            'sites' => SiteResource::collection($sites)->resolve(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Site::class);

        $servers = $request->user()
            ->servers()
            ->connected()
            ->orderBy('name')
            ->get(['uuid', 'name', 'host', 'profile']);

        return Inertia::render('sites/Create', [
            'servers' => $servers,
            'selectedServerId' => $request->string('server')->toString() ?: null,
        ]);
    }

    public function store(StoreSiteRequest $request, CreateSite $createSite): RedirectResponse
    {
        $site = $createSite->handle(
            $request->user(),
            $request->TargetServer(),
            $request->siteAttributes()
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Site created successfully.'),
        ]);

        return to_route('sites.show', $site);
    }

    public function show(Request $request, Site $site): Response
    {
        Gate::authorize('view', $site);

        $site->load(['server', 'currentRelease']);

        $releases = $site->releases()
            ->latest()
            ->limit(10)
            ->get()
            ->each(fn (Release $release) => $release->setRelation('site', $site));

        $activeOperation = Operation::query()
            ->where('server_id', $site->server_id)
            ->whereIn('type', ['deploy', 'rollback'])
            ->whereIn('status', ['pending', 'running'])
            ->where('plan_snapshot->site_id', $site->id)
            ->latest()
            ->first();

        $githubConnection = $request->user()->githubConnection;

        return Inertia::render('sites/Show', [
            'site' => SiteResource::make($site)->resolve(),
            'releases' => ReleaseResource::collection($releases)->resolve(),
            'githubConnected' => $githubConnection !== null,
            'githubUsername' => $githubConnection?->username,
            'activeOperation' => $activeOperation === null ? null : [
                'uuid' => $activeOperation->uuid,
                'type' => $activeOperation->type,
                'status' => $activeOperation->status,
                'failure_message' => $activeOperation->failure_message,
            ],
        ]);
    }
}

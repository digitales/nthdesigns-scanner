<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteSearchRequest;
use App\Http\Resources\SiteSearchResource;
use App\Services\SiteSearch\SiteSearchService;
use Inertia\Inertia;
use Inertia\Response;

class SiteSearchController extends Controller
{
    public function index(SiteSearchRequest $request, SiteSearchService $siteSearch): Response
    {
        $query = trim((string) $request->input('q', ''));
        $result = $siteSearch->search($request->user(), $query);

        return Inertia::render('Find/Index', SiteSearchResource::format($query, $result));
    }
}

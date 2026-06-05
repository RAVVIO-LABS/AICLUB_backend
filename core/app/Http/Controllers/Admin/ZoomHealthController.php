<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Zoom\LmsZoomService;

class ZoomHealthController extends Controller
{
    public function index()
    {
        $pageTitle = 'Zoom Health Check';
        $report = session('zoom_health_report');

        return view('admin.system.zoom_health', compact('pageTitle', 'report'));
    }

    public function run(LmsZoomService $zoomService)
    {
        $report = $zoomService->healthReport();

        $notify[] = [$report['oauth']['ok'] ? 'success' : 'error', $report['oauth']['ok'] ? 'Zoom health check completed' : 'Zoom OAuth check failed'];

        return back()->withNotify($notify)->with('zoom_health_report', $report);
    }
}

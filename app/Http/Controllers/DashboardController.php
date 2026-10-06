<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboard,
    ) {
    }

    public function index()
    {
        return view('dashboard', [
            'stats' => $this->dashboard->getStats(),
        ]);
    }
}
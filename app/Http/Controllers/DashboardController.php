<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Placeholder dashboard so the authenticated layout has a real route to
 * render against. Role-specific dashboards (Patient / Doctor / Facility
 * Admin / Reception / Lab / Pharmacy / Platform Admin) are a later phase —
 * this only proves the layout, navbar, sidebar, and middleware chain work.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}

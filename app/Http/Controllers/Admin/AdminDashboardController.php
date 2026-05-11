<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WwphJob;
use App\Models\JobApplication;
use App\Models\Payment;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalJobs = WwphJob::count();
        $totalApplications = JobApplication::count();

        // ✅ Fixed roles — employers are role 2, candidates are role 1
        $totalEmployers = User::where('role', 2)->count();
        $totalCandidates = User::where('role', 1)->count();

        $thisMonthRevenue = Payment::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        // ✅ Fixed — subMonth() called once and stored
        $lastMonth = now()->subMonth();
        $lastMonthRevenue = Payment::whereYear('created_at', $lastMonth->year)
            ->whereMonth('created_at', $lastMonth->month)
            ->sum('amount');

        // ✅ Wrapped in try-catch in case Payment->user relationship is missing
        try {
            $recentPayments = Payment::with('employer')->latest()->take(5)->get();
        } catch (\Exception $e) {
            $recentPayments = Payment::latest()->take(5)->get();
        }

        return response()->json([
            "overview" => [
                "jobs"         => $totalJobs,
                "applications" => $totalApplications,
                "employers"    => $totalEmployers,
                "candidates"   => $totalCandidates,
            ],
            "revenue" => [
                "this_month" => $thisMonthRevenue,
                "last_month" => $lastMonthRevenue,
            ],
            "recent_payments" => $recentPayments,
        ]);
    }
}
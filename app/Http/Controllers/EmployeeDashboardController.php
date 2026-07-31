<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Quotation; // Assuming you have a Quotation model
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon; // For date calculations

class EmployeeDashboardController extends Controller
{
    public function index()
    {
        $employeeId = Auth::id(); // Get the ID of the currently logged-in employee

        // --- Clients Data ---
        $totalClients = Client::where('employee_id', $employeeId)->count();
        $recentClients = Client::where('employee_id', $employeeId)
                               ->latest()
                               ->take(5) // Get the 5 most recent clients
                               ->get();

        // --- Quotations Data ---
        $totalQuotations = Quotation::where('employee_id', $employeeId)->count();
        // You might want to add counts for 'pending', 'approved', 'rejected' quotations later.

        // --- Invoices Data ---
        $totalInvoices = Invoice::where('employee_id', $employeeId)->count();

        $overdueInvoicesCount = Invoice::where('employee_id', $employeeId)
                                        ->where('status', 'pending') // Or 'partially_paid' if you mark those overdue too
                                        ->where('due_date', '<', Carbon::now())
                                        ->count();

        // Calculate total outstanding amount
        $totalOutstandingAmount = Invoice::where('employee_id', $employeeId)
                                         ->whereIn('status', ['pending', 'partially_paid'])
                                         ->sum(DB::raw('total_amount - amount_paid'));


        // Get invoice status counts
        $invoiceStatusCounts = Invoice::where('employee_id', $employeeId)
                                      ->select('status', DB::raw('count(*) as count'))
                                      ->groupBy('status')
                                      ->pluck('count', 'status')
                                      ->toArray();

        // Ensure all statuses are present in the array for the chart
        $allStatuses = ['pending', 'partially_paid', 'paid', 'overdue'];
        foreach ($allStatuses as $status) {
            if (!isset($invoiceStatusCounts[$status])) {
                $invoiceStatusCounts[$status] = 0;
            }
        }
        // You might want to update the 'overdue' count separately if it's based on date, not status field directly
        // For accurate overdue count in the chart, combine 'pending' and 'partially_paid' that are overdue.
        // For simplicity in the example, I'm using the dedicated $overdueInvoicesCount for the card
        // and showing the 'overdue' status from the status field if it exists.
        // If your 'overdue' status is dynamically calculated, you'd need to adjust the chart data.
        // For this example, let's assume 'overdue' is a direct status in your DB.

        return view('employee.dashboard', compact(
            'totalClients',
            'recentClients',
            'totalQuotations',
            'totalInvoices',
            'overdueInvoicesCount',
            'totalOutstandingAmount',
            'invoiceStatusCounts'
        ));
    }
}
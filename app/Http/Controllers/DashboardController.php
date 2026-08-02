<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! request()->attributes->has('currentCompany')) {
            $centralUrl = sprintf(
                '%s://%s/client-portal',
                config('saas.scheme', 'https'),
                config('saas.central_domain')
            );

            return redirect()->away($centralUrl);
        }

        return match ($user->role) {
            UserRoleEnum::Admin => $this->adminDashboard(),
            UserRoleEnum::Employee => $this->employeeDashboard(),
            UserRoleEnum::Customer => $this->customerDashboard(),
            default => view('dashboard'),
        };
    }

    public function adminDashboard(): View
    {
        $outstandingAmount = $this->invoiceOutstandingQuery()->sum(DB::raw('total_amount - amount_paid'));
        $overdueInvoices = $this->overdueInvoiceQuery()->count();
        $paymentsThisMonth = Payment::whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $expensesThisMonth = Expense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $netProfitThisMonth = $paymentsThisMonth - $expensesThisMonth;
        $pendingQuotations = Quotation::whereIn('status', ['draft', 'sent'])->count();

        $recentInvoices = Invoice::with('client')
            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->latest()
            ->take(5)
            ->get();

        $recentPayments = Payment::with('invoice.client')
            ->latest('payment_date')
            ->take(5)
            ->get();

        $recentExpenses = Expense::with('recordedBy:id,name,email')
            ->latest('expense_date')
            ->take(5)
            ->get();

        $systemCounts = [
            'users' => User::count(),
            'clients' => Client::count(),
            'invoices' => Invoice::count(),
        ];

        return view('admin.dashboard', compact(
            'outstandingAmount',
            'overdueInvoices',
            'paymentsThisMonth',
            'expensesThisMonth',
            'netProfitThisMonth',
            'pendingQuotations',
            'recentInvoices',
            'recentPayments',
            'recentExpenses',
            'systemCounts'
        ));
    }

    public function employeeDashboard(): View
    {
        $employeeId = Auth::id();

        $outstandingAmount = $this->invoiceOutstandingQuery($employeeId)->sum(DB::raw('total_amount - amount_paid'));
        $overdueInvoices = $this->overdueInvoiceQuery($employeeId)->count();
        $pendingQuotations = Quotation::where('employee_id', $employeeId)->whereIn('status', ['draft', 'sent'])->count();
        $paymentsThisMonth = Payment::whereHas('invoice', fn ($query) => $query->where('employee_id', $employeeId))
            ->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
        $expensesThisMonth = Expense::where('recorded_by_user_id', $employeeId)
            ->whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $recentInvoices = Invoice::with('client')
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->latest()
            ->take(5)
            ->get();

        $recentQuotations = Quotation::with('client')
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['draft', 'sent'])
            ->latest()
            ->take(5)
            ->get();

        return view('employee.dashboard', compact(
            'outstandingAmount',
            'overdueInvoices',
            'pendingQuotations',
            'paymentsThisMonth',
            'expensesThisMonth',
            'recentInvoices',
            'recentQuotations'
        ));
    }

    public function customerDashboard(): View
    {
        $client = Auth::user()->clients;

        $outstandingAmount = 0;
        $openInvoices = collect();
        $paidInvoicesCount = 0;

        if ($client) {
            $outstandingAmount = $this->invoiceOutstandingQuery(null, $client->id)->sum(DB::raw('total_amount - amount_paid'));
            $openInvoices = Invoice::where('client_id', $client->id)
                ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                ->latest()
                ->take(5)
                ->get();
            $paidInvoicesCount = Invoice::where('client_id', $client->id)->where('status', 'paid')->count();
        }

        return view('customer.dashboard', compact('client', 'outstandingAmount', 'openInvoices', 'paidInvoicesCount'));
    }

    public function employeePanel(): View
    {
        return $this->employeeDashboard();
    }

    public function customerInvoices(): View
    {
        $invoices = Auth::user()->invoices;

        return view('customer.invoices', compact('invoices'));
    }

    private function invoiceOutstandingQuery(?int $employeeId = null, ?int $clientId = null)
    {
        return Invoice::query()
            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId));
    }

    private function overdueInvoiceQuery(?int $employeeId = null)
    {
        return Invoice::query()
            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->whereDate('due_date', '<', Carbon::today())
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId));
    }
}

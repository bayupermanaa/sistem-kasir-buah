<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SalesReportController extends Controller
{
    public function menu(): View
    {
        return view('reports.index');
    }

    public function financial(Request $request, SalesReportService $salesReportService, string $section): View
    {
        $view = match ($section) {
            'expenses' => 'reports.expenses',
            'profit-loss' => 'reports.profit-loss',
            'cash-flow' => 'reports.cash-flow',
            'balance-sheet' => 'reports.balance-sheet',
            default => abort(404),
        };

        return view($view, $salesReportService->buildFinancialPage($request, $request->user(), $section));
    }
    public function index(Request $request, SalesReportService $salesReportService): View
    {
        return view('reports.sales', $salesReportService->build($request, $request->user()));
    }

    public function exportPdf(Request $request, SalesReportService $salesReportService): Response
    {
        $report = $salesReportService->build($request, $request->user());
        $pdf = Pdf::loadView('reports.pdf', $report)
            ->setPaper('a4', 'landscape');

        $filename = $salesReportService->exportFilename($report['filters'], 'pdf');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportExcel(Request $request, SalesReportService $salesReportService): Response
    {
        $report = $salesReportService->build($request, $request->user());
        $csv = $salesReportService->exportCsv($report);
        $filename = $salesReportService->exportFilename($report['filters'], 'csv');

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}

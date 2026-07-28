<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SalesReportController extends Controller
{
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

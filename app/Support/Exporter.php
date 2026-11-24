<?php

namespace App\Support;

use App\Exports\TableExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/** Satu pintu untuk unduh tabel sebagai Excel (.xlsx) atau PDF, dipakai semua halaman laporan. */
class Exporter
{
    /**
     * @param  array<int,string>  $headings
     * @param  array<int,array<int,mixed>>  $rows
     */
    public static function download(string $format, string $basename, string $title, string $subtitle, array $headings, array $rows, bool $landscape = true)
    {
        if ($format === 'pdf') {
            return Pdf::loadView('pdf.table', compact('title', 'subtitle', 'headings', 'rows'))
                ->setPaper('a4', $landscape ? 'landscape' : 'portrait')
                ->download($basename.'.pdf');
        }

        return Excel::download(new TableExport($headings, $rows, $title), $basename.'.xlsx');
    }
}

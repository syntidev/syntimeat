<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export genérico: colección + encabezados + nombre de hoja.
 * Usado por ContingencyController y CatalogController para generar plantillas.
 */
class SimpleExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly Collection $data,
        private readonly array $headings,
        private readonly string $title,
    ) {}

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }
}

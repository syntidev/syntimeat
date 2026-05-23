<?php

declare(strict_types=1);

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import helper que no hace nada por sí solo.
 * Se usa junto a Excel::toCollection() para obtener la colección de filas.
 */
class EmptyImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void {}
}

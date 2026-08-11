<?php

namespace App\Services\Importacion;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;


class RangoUtil implements IReadFilter
{
    private readonly int $ultimaColumna;

    public function __construct(string $ultimaColumna, private readonly int $maximoFilas)
    {
        $this->ultimaColumna = Coordinate::columnIndexFromString($ultimaColumna);
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row > $this->maximoFilas) {
            return false;
        }

        return Coordinate::columnIndexFromString($columnAddress) <= $this->ultimaColumna;
    }
}

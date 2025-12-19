<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class GenericExport implements FromCollection
{
    public function __construct(public $rows) {}
    public function collection() { return $this->rows; }
}

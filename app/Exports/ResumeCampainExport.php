<?php

namespace App\Exports;

use App\Exports\Sheets\ListadoAsignacionSheet;
use App\Exports\Sheets\ListadoLlamadasSheet;
use App\Exports\Sheets\ListadoGestionesSheet;
use App\Exports\Sheets\ListadoPagosSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ResumeCampainExport implements WithMultipleSheets
{
    protected $campainId;
    protected $userName;

    public function __construct($campainId, $userName)
    {
        $this->campainId = $campainId;
        $this->userName = $userName;
    }

    public function sheets(): array
    {
        return [
            'Listado Asignacion' => new ListadoAsignacionSheet($this->campainId, $this->userName),
            'Listado Llamadas' => new ListadoLlamadasSheet($this->campainId, $this->userName),
            'Listado Gestiones' => new ListadoGestionesSheet($this->campainId, $this->userName),
            'Listado Pagos' => new ListadoPagosSheet($this->campainId, $this->userName),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\InventoryCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    public function __invoke(InventoryCsv $inventory): StreamedResponse
    {
        return $inventory->export();
    }
}

<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Receive       = 'receive';
    case Sale          = 'sale';
    case SaleBackorder = 'sale_backorder';
    case Reserve       = 'reserve';
    case Release       = 'release';
    case Adjust        = 'adjust';
    case Transfer      = 'transfer';
}

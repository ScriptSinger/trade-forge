<?php

namespace App\Enums;

enum TradeContextStatus: string
{
    case Pending = 'pending';
    case Blocked = 'blocked';   // Filtered out by criteria
    case Ready = 'ready';       // Passed all filters, ready for execution
    case Executed = 'executed'; // Order placed successfully
    case Skipped = 'skipped';   // Skipped (e.g., position already exists)
    case Failed = 'failed';     // Error during processing
}

<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Issued = 'issued';
    case PartiallyIssued = 'partially_issued';
}

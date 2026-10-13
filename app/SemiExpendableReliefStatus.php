<?php

namespace App;

enum SemiExpendableReliefStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
}

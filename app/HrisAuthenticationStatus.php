<?php

namespace App;

enum HrisAuthenticationStatus
{
    case Authenticated;
    case Rejected;
    case Unavailable;
}

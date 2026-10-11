<?php

namespace App\Exceptions\Membership;

use DomainException;

/**
 * Exception representing user-facing domain errors in the membership flow
 * (e.g., expired withdrawal window, missing active contract, invalid voucher).
 */
class MembershipDomainException extends DomainException
{
    //
}

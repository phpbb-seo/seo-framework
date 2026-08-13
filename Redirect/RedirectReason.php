<?php
declare(strict_types=1);

namespace phpbbseo\framework\Redirect;

/**
 * Explains why a redirect was decided.
 */
enum RedirectReason: string
{
    case CANONICAL_MISMATCH = 'canonical_mismatch';
    case SCHEME_NORMALIZATION = 'scheme_normalization';
    case HOST_NORMALIZATION = 'host_normalization';
    case MANUAL_RULE = 'manual_rule';
}

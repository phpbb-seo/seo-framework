<?php
declare(strict_types=1);

namespace phpbbseo\framework\Url;

/**
 * Classifies query parameters for SEO URL handling.
 */
enum QueryParamClassification
{
    /** Must be preserved in canonical SEO URL (e.g. 't', 'f', 'u') */
    case REQUIRED;
    /** Pagination offset — converted to page segment, not preserved raw */
    case PAGINATION;
    /** Session ID — dropped from canonical */
    case IGNORED;
    /** UTM-style tracking — always stripped */
    case TRACKING;
    /** Unknown param — never propagated automatically */
    case UNKNOWN;
}

<?php
declare(strict_types=1);

namespace phpbbseo\framework\Url;

/**
 * Converts between phpBB raw 'start' offsets and human SEO page numbers.
 */
class PaginationResolver
{
    /**
     * Convert phpBB start offset to a 1-based SEO page number.
     * Page 1 is represented as null (base URL, no page segment).
     */
    public function startToPage(int $start, int $perPage): ?int
    {
        if ($perPage <= 0) {
            return null;
        }
        $page = (int) floor($start / $perPage) + 1;
        return $page > 1 ? $page : null;
    }

    /**
     * Convert a SEO page number back to a phpBB start offset.
     */
    public function pageToStart(int $page, int $perPage): int
    {
        if ($page <= 1 || $perPage <= 0) {
            return 0;
        }
        return ($page - 1) * $perPage;
    }
}

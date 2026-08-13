<?php
declare(strict_types=1);

namespace phpbbseo\framework\Url;

/**
 * Classifies and filters query parameters per resource type.
 * Unknown parameters are NEVER propagated into canonical SEO URLs.
 */
class QueryParameterPolicy
{
    /**
     * Per-script classifications of query parameters.
     * Structure: [ script => [ param => classification ] ]
     *
     * @var array<string, array<string, QueryParamClassification>>
     */
    private const MAP = [
        'viewtopic.php' => [
            't'     => QueryParamClassification::REQUIRED,
            'f'     => QueryParamClassification::IGNORED,   // forum hint, not required
            'start' => QueryParamClassification::PAGINATION,
            'sid'   => QueryParamClassification::IGNORED,
            'hilit' => QueryParamClassification::IGNORED,
            'utm_source' => QueryParamClassification::TRACKING,
            'utm_medium' => QueryParamClassification::TRACKING,
            'utm_campaign' => QueryParamClassification::TRACKING,
        ],
        'viewforum.php' => [
            'f'     => QueryParamClassification::REQUIRED,
            'start' => QueryParamClassification::PAGINATION,
            'sid'   => QueryParamClassification::IGNORED,
            'utm_source' => QueryParamClassification::TRACKING,
            'utm_medium' => QueryParamClassification::TRACKING,
            'utm_campaign' => QueryParamClassification::TRACKING,
        ],
        'memberlist.php' => [
            'mode'  => QueryParamClassification::REQUIRED,
            'u'     => QueryParamClassification::REQUIRED,
            'sid'   => QueryParamClassification::IGNORED,
        ],
    ];

    public function classify(string $script, string $param): QueryParamClassification
    {
        return self::MAP[$script][$param] ?? QueryParamClassification::UNKNOWN;
    }

    /**
     * Extract required identity params from a query array for the given script.
     *
     * @param array<string, string> $query
     * @return array<string, string>
     */
    public function extractRequired(string $script, array $query): array
    {
        $result = [];
        foreach ($query as $key => $value) {
            if ($this->classify($script, $key) === QueryParamClassification::REQUIRED) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Extract the raw pagination offset (start=N) from a query array.
     */
    public function extractPaginationOffset(string $script, array $query): ?int
    {
        foreach ($query as $key => $value) {
            if ($this->classify($script, $key) === QueryParamClassification::PAGINATION) {
                $val = filter_var($value, FILTER_VALIDATE_INT);
                return ($val !== false && $val >= 0) ? $val : null;
            }
        }
        return null;
    }
}

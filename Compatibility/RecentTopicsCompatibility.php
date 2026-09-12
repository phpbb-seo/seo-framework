<?php
declare(strict_types=1);

namespace phpbbseo\framework\Compatibility;

use phpbbseo\framework\Configuration\ConfigurationProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Compatibility shim for the RecentTopics extension (paybas/recenttopics & avathar/recenttopics).
 *
 * RecentTopics generates links by appending raw '&view=unread#unread' or '&p=...' query strings
 * directly to the topic URL. When SEO permalinks are enabled (slash-terminated), this results in
 * malformed URLs like '/topic/slug-123/&view=unread#unread'.
 * This subscriber intercepts the template array and normalizes '&' / '&amp;' immediately following
 * a trailing slash into a proper '?' query delimiter.
 */
class RecentTopicsCompatibility implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigurationProvider $configProvider
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'avathar.recenttopics.modify_tpl_ary' => 'onRecentTopicsModifyTplAry',
            'paybas.recenttopics.modify_tpl_ary'  => 'onRecentTopicsModifyTplAry',
        ];
    }

    public function onRecentTopicsModifyTplAry($event): void
    {
        if (!$this->configProvider->isRewriteEnabled()) {
            return;
        }

        $tplAry = $event['tpl_ary'] ?? [];
        if (!is_array($tplAry)) {
            return;
        }

        $modified = false;
        foreach (['U_NEWEST_POST', 'U_LAST_POST', 'U_VIEW_TOPIC'] as $key) {
            if (isset($tplAry[$key]) && is_string($tplAry[$key])) {
                // Correct naive 3rd-party extension concatenation where & or &amp; follows a slash-terminated SEO URL
                $fixed = preg_replace('~(/)(?:&amp;|&)([^#]*)~i', '$1?$2', $tplAry[$key]);
                if ($fixed !== null && $fixed !== $tplAry[$key]) {
                    $tplAry[$key] = $fixed;
                    $modified = true;
                }
            }
        }

        if ($modified) {
            $event['tpl_ary'] = $tplAry;
        }
    }
}
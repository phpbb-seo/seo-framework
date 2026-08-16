<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

/**
 * Parses script names and query parameters to identify public public resources (Forums, Topics, Members).
 */
class ResourceDetector
{
    /**
     * Detects if the target script and query parameters map to a public resource.
     *
     * @param string $script The target script path or basename (e.g. 'viewtopic.php')
     * @param array<string, mixed> $params The parsed query parameters
     * @return ResourceTarget|null The target resource info, or null if unrecognized
     */
    public function detect(string $script, array $params): ?ResourceTarget
    {
        $script = ltrim(basename($script), '/');

        switch ($script) {
            case 'viewforum.php':
                $forumId = isset($params['f']) ? (int) $params['f'] : null;
                if ($forumId !== null && $forumId > 0) {
                    $start = isset($params['start']) ? (int) $params['start'] : 0;
                    return new ResourceTarget('forum', $forumId, ['start' => $start]);
                }
                break;

            case 'viewtopic.php':
                $topicId = isset($params['t']) ? (int) $params['t'] : null;
                $postId = isset($params['p']) ? (int) $params['p'] : null;
                $start = isset($params['start']) ? (int) $params['start'] : 0;

                // Priority 1: If explicit topic_id is available (even with p), treat as topic target with post_id attribute
                if ($topicId !== null && $topicId > 0) {
                    $paginationParams = ['start' => $start];
                    if ($postId !== null && $postId > 0) {
                        $paginationParams['post_id'] = $postId;
                    }
                    return new ResourceTarget('topic', $topicId, $paginationParams);
                }

                // Priority 2: Isolated post_id without topic_id
                if ($postId !== null && $postId > 0) {
                    return new ResourceTarget('post', $postId);
                }
                break;

            case 'memberlist.php':
                $userId = isset($params['u']) ? (int) $params['u'] : null;
                $mode = $params['mode'] ?? '';
                if ($userId !== null && $userId > 1 && $mode === 'viewprofile') {
                    return new ResourceTarget('member', $userId);
                }
                $groupId = isset($params['g']) ? (int) $params['g'] : null;
                if ($groupId !== null && $groupId > 0 && $mode === 'group') {
                    return new ResourceTarget('group', $groupId);
                }
                break;
        }

        return null;
    }
}

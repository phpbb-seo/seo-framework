<?php
declare(strict_types=1);

namespace phpbbseo\framework\Controller;

use phpbb\auth\auth;
use phpbb\request\request_interface;
use phpbb\user;
use phpbbseo\framework\Backfill\Exception\BackfillLockException;
use phpbbseo\framework\Backfill\SlugBackfillManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller providing the ACP AJAX runner endpoint for iterative slug backfilling.
 */
class BackfillController
{
    public const CSRF_FORM_NAME = 'pseo_sitemap_rebuild';

    public function __construct(
        private readonly SlugBackfillManager $backfillManager,
        private readonly request_interface $request,
        private readonly user $user,
        private readonly auth $auth
    ) {}

    /**
     * Process one stepped batch via AJAX.
     */
    public function batchAction(): Response
    {
        // 1. Session & Registration Validation
        if (empty($this->user->data['is_registered']) || (int) ($this->user->data['user_type'] ?? 0) === USER_IGNORE) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'Unauthorized session. Please log in.',
            ], Response::HTTP_FORBIDDEN);
        }

        // 2. Administrator Authorization Check
        if (!$this->auth->acl_get('a_') || !$this->auth->acl_get('a_board')) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'Access denied. Administrative privileges required.',
            ], Response::HTTP_FORBIDDEN);
        }

        // 3. CSRF Form Token Validation
        if (!check_form_key(self::CSRF_FORM_NAME)) {
            return new JsonResponse([
                'success' => false,
                'error'   => $this->user->lang('FORM_INVALID'),
            ], Response::HTTP_BAD_REQUEST);
        }

        // 4. Extract and validate parameters
        $lastId = (int) $this->request->variable('last_id', 0);
        $batchSize = (int) $this->request->variable('batch_size', SlugBackfillManager::DEFAULT_BATCH_SIZE);

        $lastId = max(0, $lastId);
        $batchSize = min(max($batchSize, 1), SlugBackfillManager::MAX_BATCH_SIZE);

        // 5. Execute batch via BackfillManager
        try {
            $result = $this->backfillManager->backfillBatch(
                resourceType: 'topic',
                lastId: $lastId,
                batchSize: $batchSize,
                onlyMissing: true
            );

            return new JsonResponse([
                'success'   => true,
                'processed' => $result->processed,
                'last_id'   => $result->lastId,
                'remaining' => $result->remaining,
                'completed' => $result->completed,
                'elapsed'   => $result->elapsed,
            ]);
        } catch (BackfillLockException $e) {
            return new JsonResponse([
                'success' => false,
                'error'   => $e->getMessage(),
                'locked'  => true,
            ], Response::HTTP_CONFLICT);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

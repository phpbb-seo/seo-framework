<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use phpbb\auth\auth;
use phpbb\request\request_interface;
use phpbb\user;
use phpbbseo\framework\Backfill\BackfillBatchResult;
use phpbbseo\framework\Backfill\Exception\BackfillLockException;
use phpbbseo\framework\Backfill\SlugBackfillManager;
use phpbbseo\framework\Controller\BackfillController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BackfillControllerTest extends TestCase
{
    private SlugBackfillManager $managerMock;
    private request_interface $requestMock;
    private user $userMock;
    private auth $authMock;
    private array $postVars = [];

    protected function setUp(): void
    {
        $this->managerMock = $this->createMock(SlugBackfillManager::class);
        $this->requestMock = $this->createMock(request_interface::class);
        $this->userMock = $this->createMock(user::class);
        $this->authMock = $this->createMock(auth::class);
        $this->postVars = [];

        $this->userMock->data = [
            'user_id'          => 2,
            'is_registered'    => 1,
            'user_type'        => 3,
            'user_form_salt'   => 'testsalt',
        ];
        $this->userMock->method('lang')->willReturnCallback(fn($k) => $k);

        $this->requestMock->method('is_set_post')->willReturnCallback(function ($var) {
            return isset($this->postVars[$var]);
        });
        $this->requestMock->method('variable')->willReturnCallback(function ($var, $default) {
            return $this->postVars[$var] ?? $default;
        });

        $GLOBALS['request'] = $this->requestMock;
        $GLOBALS['user'] = $this->userMock;
        $GLOBALS['config'] = [
            'form_token_lifetime'   => 86400,
            'form_token_sid_guests' => 0,
        ];
    }

    private function createController(): BackfillController
    {
        return new BackfillController(
            $this->managerMock,
            $this->requestMock,
            $this->userMock,
            $this->authMock
        );
    }

    public function testUnregisteredUserReturnsForbidden(): void
    {
        $this->userMock->data = [
            'user_id'       => 1,
            'is_registered' => 0,
        ];

        $controller = $this->createController();
        $response = $controller->batchAction();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testNonAdminUserReturnsForbidden(): void
    {
        $this->authMock->method('acl_get')->willReturn(false);

        $controller = $this->createController();
        $response = $controller->batchAction();

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testInvalidCsrfTokenReturnsBadRequest(): void
    {
        $this->authMock->method('acl_get')->willReturn(true);
        $this->postVars = [];

        // check_form_key fails without valid post vars
        $controller = $this->createController();
        $response = $controller->batchAction();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testLockConflictReturnsConflictResponse(): void
    {
        $this->authMock->method('acl_get')->willReturn(true);

        $now = time() - 10;
        $token = sha1($now . 'testsalt' . BackfillController::CSRF_FORM_NAME);
        $this->postVars = [
            'creation_time' => $now,
            'form_token'    => $token,
        ];

        $this->managerMock->method('backfillBatch')->willThrowException(
            new BackfillLockException('A slug rebuild process is already running.')
        );

        $controller = $this->createController();
        $response = $controller->batchAction();

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertTrue($data['locked']);
    }

    public function testSuccessfulBatchReturnsJsonResponse(): void
    {
        $this->authMock->method('acl_get')->willReturn(true);

        $now = time() - 10;
        $token = sha1($now . 'testsalt' . BackfillController::CSRF_FORM_NAME);
        $this->postVars = [
            'creation_time' => $now,
            'form_token'    => $token,
            'last_id'       => 100,
            'batch_size'    => 500,
        ];

        $batchResult = new BackfillBatchResult(
            processed: 500,
            lastId: 600,
            remaining: 250,
            completed: false,
            elapsed: 0.05
        );

        $this->managerMock->method('backfillBatch')
            ->with('topic', 100, 500, true)
            ->willReturn($batchResult);

        $controller = $this->createController();
        $response = $controller->batchAction();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame(500, $data['processed']);
        $this->assertSame(600, $data['last_id']);
        $this->assertSame(250, $data['remaining']);
        $this->assertFalse($data['completed']);
    }
}

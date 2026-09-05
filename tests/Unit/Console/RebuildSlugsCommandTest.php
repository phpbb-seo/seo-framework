<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Backfill\BackfillBatchResult;
use phpbbseo\framework\Backfill\SlugBackfillManager;
use phpbbseo\framework\Console\RebuildSlugsCommand;
use phpbbseo\framework\Rewrite\SlugRepository;
use Symfony\Component\Console\Tester\CommandTester;

class RebuildSlugsCommandTest extends TestCase
{
    private SlugBackfillManager $backfillManager;
    private SlugRepository $slugRepository;
    private RebuildSlugsCommand $command;

    protected function setUp(): void
    {
        $this->backfillManager = $this->createMock(SlugBackfillManager::class);
        $this->slugRepository  = $this->createMock(SlugRepository::class);

        $this->command = new RebuildSlugsCommand(
            $this->backfillManager,
            $this->slugRepository
        );
    }

    public function testCommandMetadataAndAliases(): void
    {
        $this->assertSame('seo:rebuild-slugs', $this->command->getName());
        $this->assertContains('phpbbseo:rebuild-slugs', $this->command->getAliases());
    }

    public function testLockAcquisitionFailureReturnsErrorCode(): void
    {
        $this->backfillManager->method('acquireLock')->willReturn(false);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('A slug rebuild process is already running', $tester->getDisplay());
    }

    public function testDefaultExecutionIsMissingOnly(): void
    {
        $this->backfillManager->method('acquireLock')->willReturn(true);
        $this->backfillManager->expects($this->once())->method('releaseLock');

        $this->slugRepository->expects($this->exactly(3))->method('rebuildSlugs');

        $this->backfillManager->expects($this->once())
            ->method('backfillBatch')
            ->with('topic', 0, 1000, true)
            ->willReturn(new BackfillBatchResult(
                processed: 10,
                lastId: 50,
                remaining: 0,
                completed: true,
                elapsed: 0.02
            ));

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('missing-only mode', $display);
        $this->assertStringContainsString('Slug rebuild completed successfully', $display);
    }

    public function testAllOptionTriggersCompleteRebuild(): void
    {
        $this->backfillManager->method('acquireLock')->willReturn(true);
        $this->backfillManager->expects($this->once())->method('releaseLock');

        $this->backfillManager->expects($this->once())
            ->method('backfillBatch')
            ->with('topic', 0, 1000, false)
            ->willReturn(new BackfillBatchResult(
                processed: 25,
                lastId: 100,
                remaining: 0,
                completed: true,
                elapsed: 0.04
            ));

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--all' => true]);

        $this->assertSame(0, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('complete mode', $display);
    }

    public function testCustomBatchSizeOption(): void
    {
        $this->backfillManager->method('acquireLock')->willReturn(true);

        $this->backfillManager->expects($this->once())
            ->method('backfillBatch')
            ->with('topic', 0, 250, true)
            ->willReturn(new BackfillBatchResult(
                processed: 0,
                lastId: 0,
                remaining: 0,
                completed: true
            ));

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--batch-size' => 250]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('batch size: 250', $tester->getDisplay());
    }
}

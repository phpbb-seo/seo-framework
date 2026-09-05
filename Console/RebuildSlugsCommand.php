<?php
declare(strict_types=1);

namespace phpbbseo\framework\Console;

use phpbbseo\framework\Backfill\SlugBackfillManager;
use phpbbseo\framework\Rewrite\SlugRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to rebuild the framework-owned slug index from phpBB native tables.
 *
 * Employs SlugBackfillManager with zero-offset keyset pagination, multi-row DBAL insertion,
 * missing-only default mode, and mutex concurrency locking.
 */
class RebuildSlugsCommand extends Command
{
    public function __construct(
        private readonly SlugBackfillManager $backfillManager,
        private readonly SlugRepository $slugRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('seo:rebuild-slugs')
            ->setAliases(['phpbbseo:rebuild-slugs'])
            ->setDescription('Rebuilds the persistent SEO slug read-model table for all public resources.')
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Rebuild all slugs from scratch instead of processing only missing ones.'
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of topic records to process per batch (1 - 1000).',
                1000
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. Concurrency Guard
        if (!$this->backfillManager->acquireLock()) {
            $output->writeln('<error>A slug rebuild process is already running.</error>');
            return 1;
        }

        try {
            $onlyMissing = !$input->getOption('all');
            $batchSize = (int) $input->getOption('batch-size');
            $batchSize = min(max($batchSize, 1), SlugBackfillManager::MAX_BATCH_SIZE);

            $output->writeln('<info>Rebuilding persistent SEO slugs...</info>');

            // A. Forums, Members, Groups
            $output->writeln('<comment>• Rebuilding Forum slugs...</comment>');
            $this->slugRepository->rebuildSlugs('forum');

            $output->writeln('<comment>• Rebuilding Group slugs...</comment>');
            $this->slugRepository->rebuildSlugs('group');

            $output->writeln('<comment>• Rebuilding Member slugs...</comment>');
            $this->slugRepository->rebuildSlugs('member');

            // B. Topics (Keyset batching via SlugBackfillManager)
            $modeLabel = $onlyMissing ? 'missing-only' : 'complete';
            $output->writeln(sprintf('<comment>• Rebuilding Topic slugs (%s mode, batch size: %d)...</comment>', $modeLabel, $batchSize));

            $lastId = 0;
            $totalProcessed = 0;

            do {
                $result = $this->backfillManager->backfillBatch(
                    resourceType: 'topic',
                    lastId: $lastId,
                    batchSize: $batchSize,
                    onlyMissing: $onlyMissing
                );

                $totalProcessed += $result->processed;
                $lastId = $result->lastId;

                if ($result->processed > 0) {
                    $output->writeln(sprintf(
                        '  Processed %d topics (last ID: %d, remaining: %d, time: %0.3fs)',
                        $totalProcessed,
                        $result->lastId,
                        $result->remaining,
                        $result->elapsed
                    ));
                }
            } while (!$result->completed);

            $output->writeln(sprintf('<info>Slug rebuild completed successfully (%d topics processed).</info>', $totalProcessed));
            return 0; // Command::SUCCESS
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Slug rebuild failed: %s</error>', $e->getMessage()));
            return 1;
        } finally {
            $this->backfillManager->releaseLock();
        }
    }
}

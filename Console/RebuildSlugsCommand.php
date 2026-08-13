<?php
declare(strict_types=1);

namespace phpbbseo\framework\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use phpbbseo\framework\Rewrite\SlugRepository;

/**
 * Console command to rebuild the framework-owned slug index from phpBB native tables.
 */
class RebuildSlugsCommand extends Command
{
    public function __construct(
        private readonly SlugRepository $slugRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('seo:rebuild-slugs')
            ->setAliases(['phpbbseo:rebuild-slugs'])
            ->setDescription('Rebuilds the persistent SEO slug read-model table for all public resources.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Rebuilding Forum slugs...</info>');
        $this->slugRepository->rebuildSlugs('forum');

        $output->writeln('<info>Rebuilding Topic slugs...</info>');
        $this->slugRepository->rebuildSlugs('topic');

        $output->writeln('<info>Rebuilding Member slugs...</info>');
        $this->slugRepository->rebuildSlugs('member');

        $output->writeln('<info>Rebuilding Group slugs...</info>');
        $this->slugRepository->rebuildSlugs('group');

        $output->writeln('<info>All SEO slugs successfully rebuilt!</info>');
        return 0; // Command::SUCCESS
    }
}

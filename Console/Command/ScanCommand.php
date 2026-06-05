<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Console\Command;

use Etechflow\SeoAudit\Model\Scanner;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScanCommand extends Command
{
    public function __construct(
        private readonly Scanner $scanner,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('etechflow:seoaudit:scan')
            ->setDescription('Run the SEO audit across products, categories and CMS; print score + issue summary.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_GLOBAL);
        } catch (\Throwable $e) {
            // already set
        }

        $output->writeln('<info>Running SEO audit…</info>');
        $summary = $this->scanner->scan();

        $output->writeln('');
        $output->writeln(sprintf('  <comment>SEO Health Score: %d / 100</comment>', $summary['score']));
        $output->writeln(sprintf('  Checks run: %d   Total issues: %d', $summary['checks'], $summary['total']));
        $output->writeln('');
        $output->writeln('  By severity:');
        foreach ($summary['by_severity'] as $sev => $n) {
            $output->writeln(sprintf('    %-9s %d', $sev, $n));
        }
        $output->writeln('  By category:');
        foreach ($summary['by_category'] as $cat => $n) {
            $output->writeln(sprintf('    %-9s %d', $cat, $n));
        }
        $output->writeln('');
        $output->writeln('<info>Done. View details in admin: Content → SEO Audit.</info>');

        return Command::SUCCESS;
    }
}

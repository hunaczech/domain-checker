<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AsyncWhoisClient;
use App\Service\CsvReaderService;
use App\Service\DomainCheckerService;
use App\Service\DomainStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Amp\async;
use function Amp\Future\await;

#[AsCommand(
    name: 'app:check-domains',
    description: 'Check domain availability from a CSV file',
)]
final class CheckDomainsCommand extends Command
{
    private const DEFAULT_FILE = './domains.csv';
    private const DEFAULT_CONCURRENCY = 5;

    protected function configure(): void
    {
        $this
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'Path to the CSV file containing domains',
                self::DEFAULT_FILE,
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output format: table, csv, json',
                'table',
            )
            ->addOption(
                'concurrency',
                'c',
                InputOption::VALUE_REQUIRED,
                'Number of concurrent WHOIS queries',
                (string) self::DEFAULT_CONCURRENCY,
            )
            ->addOption(
                'watch',
                'w',
                InputOption::VALUE_NONE,
                'Watch the file for changes and re-check domains automatically',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getOption('file');
        $outputFormat = $input->getOption('output');
        $concurrency = (int) $input->getOption('concurrency');
        $watchMode = $input->getOption('watch');

        if ($concurrency < 1) {
            $concurrency = self::DEFAULT_CONCURRENCY;
        }

        $whoisClient = new AsyncWhoisClient();
        $csvReader = new CsvReaderService();
        $domainChecker = new DomainCheckerService($whoisClient);

        $io->title('Domain Availability Checker');
        $io->text(sprintf('Supported TLDs: .%s', implode(', .', $domainChecker->getSupportedTlds())));
        $io->text(sprintf('Concurrency: %d', $concurrency));

        if ($watchMode) {
            $io->text('<info>Watch mode enabled</info>');
            $io->text('<comment>Press Q to quit</comment>');
        }

        $io->newLine();

        // Run initial check
        $result = $this->runDomainCheck($io, $output, $csvReader, $domainChecker, $filePath, $outputFormat, $concurrency);

        if (!$watchMode) {
            return $result;
        }

        // Watch mode loop
        return $this->runWatchLoop($io, $output, $csvReader, $domainChecker, $filePath, $outputFormat, $concurrency);
    }

    private function runDomainCheck(
        SymfonyStyle $io,
        OutputInterface $output,
        CsvReaderService $csvReader,
        DomainCheckerService $domainChecker,
        string $filePath,
        string $outputFormat,
        int $concurrency,
    ): int {
        try {
            $domains = $csvReader->readDomains($filePath);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if (empty($domains)) {
            $io->warning('No valid domains found in the CSV file.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d domain(s) to check...', count($domains)));
        $io->newLine();

        $results = [];
        $progressBar = $io->createProgressBar(count($domains));
        $progressBar->start();

        // Group domains by TLD to avoid rate limiting
        $domainsByTld = $this->groupDomainsByTld($domains);

        // Process in rounds - one domain per TLD per round to spread load
        while (!empty($domainsByTld)) {
            $batch = [];

            // Take one domain from each TLD (up to concurrency limit)
            foreach ($domainsByTld as $tld => $tldDomains) {
                if (count($batch) >= $concurrency) {
                    break;
                }

                $domain = array_shift($domainsByTld[$tld]);
                $batch[] = $domain;

                if (empty($domainsByTld[$tld])) {
                    unset($domainsByTld[$tld]);
                }
            }

            // Process batch concurrently
            $futures = [];
            foreach ($batch as $domain) {
                $futures[$domain] = async(fn() => $domainChecker->check($domain));
            }

            $batchResults = await($futures);

            foreach ($batchResults as $result) {
                $results[] = $result;
                $progressBar->advance();
            }

            // Small delay between batches to avoid rate limiting
            if (!empty($domainsByTld)) {
                usleep(200000); // 200ms delay
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        $this->outputResults($io, $output, $results, $outputFormat);

        return Command::SUCCESS;
    }

    private function runWatchLoop(
        SymfonyStyle $io,
        OutputInterface $output,
        CsvReaderService $csvReader,
        DomainCheckerService $domainChecker,
        string $filePath,
        string $outputFormat,
        int $concurrency,
    ): int {
        $lastModifiedTime = @filemtime($filePath);

        // Set up non-blocking stdin
        $stdin = fopen('php://stdin', 'r');
        if ($stdin === false) {
            $io->error('Could not open stdin for reading.');
            return Command::FAILURE;
        }
        stream_set_blocking($stdin, false);

        // Save terminal settings and set to raw mode for immediate key detection
        $sttyMode = shell_exec('stty -g');
        system('stty -icanon -echo');

        $io->newLine();
        $io->text(sprintf('<comment>Watching %s for changes... (Press Q to quit)</comment>', $filePath));

        try {
            while (true) {
                // Check for keyboard input
                $read = [$stdin];
                $write = null;
                $except = null;

                if (stream_select($read, $write, $except, 0, 100000) > 0) {
                    $char = fread($stdin, 1);
                    if ($char === 'q' || $char === 'Q') {
                        $io->newLine();
                        $io->success('Watch mode terminated by user.');
                        break;
                    }
                }

                // Check for file changes
                clearstatcache(true, $filePath);
                $currentModifiedTime = @filemtime($filePath);

                if ($currentModifiedTime !== false && $currentModifiedTime !== $lastModifiedTime) {
                    $lastModifiedTime = $currentModifiedTime;

                    $io->newLine();
                    $io->section(sprintf('File changed at %s - Re-checking domains...', date('H:i:s')));

                    $this->runDomainCheck($io, $output, $csvReader, $domainChecker, $filePath, $outputFormat, $concurrency);

                    $io->newLine();
                    $io->text(sprintf('<comment>Watching %s for changes... (Press Q to quit)</comment>', $filePath));
                }

                // Small sleep to avoid busy waiting
                usleep(100000); // 100ms
            }
        } finally {
            // Restore terminal settings
            if ($sttyMode !== null) {
                system('stty ' . $sttyMode);
            }
            fclose($stdin);
        }

        return Command::SUCCESS;
    }

    private function outputResults(SymfonyStyle $io, OutputInterface $output, array $results, string $format): void
    {
        match ($format) {
            'csv' => $this->outputCsv($output, $results),
            'json' => $this->outputJson($output, $results),
            default => $this->outputTable($io, $results),
        };
    }

    private function outputTable(SymfonyStyle $io, array $results): void
    {
        $table = new Table($io);
        $table->setHeaders(['Domain', 'TLD', 'Status']);

        $available = 0;
        $registered = 0;
        $errors = 0;

        foreach ($results as $result) {
            $statusText = match ($result->status) {
                DomainStatus::Available => '<fg=green>AVAILABLE</>',
                DomainStatus::Registered => '<fg=red>REGISTERED</>',
                DomainStatus::Error => sprintf('<fg=yellow>ERROR: %s</>', $result->errorMessage ?? 'Unknown'),
            };

            match ($result->status) {
                DomainStatus::Available => $available++,
                DomainStatus::Registered => $registered++,
                DomainStatus::Error => $errors++,
            };

            $table->addRow([
                $result->domain,
                $result->tld ? '.' . $result->tld : '-',
                $statusText,
            ]);
        }

        $table->render();

        $io->newLine();
        $io->text([
            sprintf('<fg=green>Available: %d</>', $available),
            sprintf('<fg=red>Registered: %d</>', $registered),
            sprintf('<fg=yellow>Errors: %d</>', $errors),
        ]);
    }

    private function outputCsv(OutputInterface $output, array $results): void
    {
        $output->writeln('domain,tld,status,error');

        foreach ($results as $result) {
            $output->writeln(sprintf(
                '%s,%s,%s,%s',
                $result->domain,
                $result->tld ?? '',
                $result->status->value,
                $result->errorMessage ?? '',
            ));
        }
    }

    private function outputJson(OutputInterface $output, array $results): void
    {
        $data = array_map(fn($result) => [
            'domain' => $result->domain,
            'tld' => $result->tld,
            'status' => $result->status->value,
            'error' => $result->errorMessage,
        ], $results);

        $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string> $domains
     * @return array<string, array<string>>
     */
    private function groupDomainsByTld(array $domains): array
    {
        $grouped = [];

        foreach ($domains as $domain) {
            $parts = explode('.', $domain);
            $tld = strtolower(end($parts));
            $grouped[$tld][] = $domain;
        }

        return $grouped;
    }
}

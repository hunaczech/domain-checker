<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CsvReaderService;
use App\Service\DomainCheckerService;
use App\Service\DomainStatus;
use App\Service\WhoisClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-domains',
    description: 'Check domain availability from a CSV file',
)]
final class CheckDomainsCommand extends Command
{
    private const DEFAULT_FILE = './domains.csv';
    private const DELAY_SECONDS = 1;

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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getOption('file');
        $outputFormat = $input->getOption('output');

        $whoisClient = new WhoisClient();
        $csvReader = new CsvReaderService();
        $domainChecker = new DomainCheckerService($whoisClient);

        $io->title('Domain Availability Checker');
        $io->text(sprintf('Supported TLDs: .%s', implode(', .', $domainChecker->getSupportedTlds())));
        $io->newLine();

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

        foreach ($domains as $index => $domain) {
            $result = $domainChecker->check($domain);
            $results[] = $result;
            $progressBar->advance();

            if ($index < count($domains) - 1) {
                sleep(self::DELAY_SECONDS);
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        $this->outputResults($io, $output, $results, $outputFormat);

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
}

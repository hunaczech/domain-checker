<?php

declare(strict_types=1);

namespace App\Service;

final class CsvReaderService
{
    /**
     * @return string[]
     * @throws \RuntimeException
     */
    public function readDomains(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException(sprintf('File not found: %s', $filePath));
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Cannot open file: %s', $filePath));
        }

        $domains = [];
        $header = null;
        $domainColumnIndex = null;

        while (($row = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map('strtolower', array_map('trim', $row));
                $domainColumnIndex = array_search('domain', $header, true);

                if ($domainColumnIndex === false) {
                    fclose($handle);
                    throw new \RuntimeException('CSV file must have a "domain" column header');
                }

                continue;
            }

            if (isset($row[$domainColumnIndex])) {
                $domain = trim($row[$domainColumnIndex]);
                if ($domain !== '' && $this->isValidDomain($domain)) {
                    $domains[] = strtolower($domain);
                }
            }
        }

        fclose($handle);

        return $domains;
    }

    private function isValidDomain(string $domain): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+$/', $domain);
    }
}

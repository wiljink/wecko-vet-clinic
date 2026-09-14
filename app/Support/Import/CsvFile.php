<?php

namespace App\Support\Import;

/**
 * Minimal, dependency-free CSV reader/writer for the import tooling.
 * Handles a UTF-8 BOM and quoted fields (via the native fgetcsv).
 */
class CsvFile
{
    /**
     * Read a CSV into rows keyed by their (canonical) header.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public static function read(string $path, Importer $importer): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Could not open the uploaded file.');
        }

        $rawHeaders = fgetcsv($handle);

        if ($rawHeaders === false || $rawHeaders === null) {
            fclose($handle);
            throw new \RuntimeException('The file is empty.');
        }

        // strip UTF-8 BOM from the first header
        $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeaders[0]);
        $headers = $importer->mapHeaders($rawHeaders);

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || (count($data) === 1 && trim((string) $data[0]) === '')) {
                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** Build a template CSV string for an importer. */
    public static function template(Importer $importer): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $importer->headers());
        foreach ($importer->sampleRows() as $row) {
            fputcsv($out, $row);
        }
        rewind($out);

        return stream_get_contents($out);
    }
}

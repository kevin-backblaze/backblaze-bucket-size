<?php
/**
 * Script: b2-bucket-size-native.php
 * Author: Kevin Lott
 * Description:
 *     Scans a Backblaze B2 bucket using the B2 Native API to list folders and report total file count and size.
 *     Supports top-level folder scans and outputs in text, JSON, or CSV format.
 *
 * Parameters:
 *     <bucket-name>            Required. The bucket name to scan.
 *     [folder-prefix/]         Optional. If set, scans just that folder.
 *
 * Flags:
 *     --output=text|json|csv   Choose output format (default: "text").
 *     --verbose                Show progress and verbose output.
 *     --versions               Include versions and deletions (default: true).
 */

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

// === LOAD ENV ===
$dotenv = Dotenv::createImmutable(__DIR__ . '/../', 'eu-master.env');
$dotenv->load();

// Account credentials
$accountId = '019089605497';
$applicationKey = '003b74227f2337d8f3d8b47a1f8b12b31b89ceb685';

// Parse CLI arguments
$bucketName = $argv[1] ?? null;
$prefixArg = ($argv[2] ?? '') && str_starts_with($argv[2], '--') ? '' : ($argv[2] ?? '');

$flags = implode(' ', $argv);
$verbose = str_contains($flags, '--verbose');
$includeVersions = !str_contains($flags, '--versions=false');

preg_match('/--output=(\w+)/', $flags, $matches);
$output = $matches[1] ?? 'text';

if (!$bucketName) {
    echo "Usage: php b2-bucket-size-native.php <bucket> [prefix/] [--verbose] [--output=text|json|csv] [--versions=true|false]\n";
    exit(1);
}

/**
 * Converts a byte count into a human-readable format (e.g., GB, MB).
 *
 * @param int $bytes     Number of bytes.
 * @param int $precision Decimal places.
 * @return string        Human-readable size string.
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

$client = new Client(['base_uri' => 'https://api.backblazeb2.com']);

// Step 1: Authorize account
$response = $client->request('GET', '/b2api/v2/b2_authorize_account', [
    'auth' => [$accountId, $applicationKey],
]);
$data = json_decode($response->getBody(), true);
$authToken = $data['authorizationToken'];
$apiUrl = $data['apiUrl'];

// Step 2: Find bucket ID
$response = $client->request('POST', $apiUrl . '/b2api/v2/b2_list_buckets', [
    'headers' => ['Authorization' => $authToken],
    'json' => ['accountId' => $accountId],
]);
$buckets = json_decode($response->getBody(), true)['buckets'];
$bucket = array_filter($buckets, fn($b) => $b['bucketName'] === $bucketName);
$bucketId = array_values($bucket)[0]['bucketId'] ?? null;

if (!$bucketId) {
    echo "Bucket not found.\n";
    exit(1);
}

/**
 * Scans all files in the specified B2 bucket and aggregates totals by folder.
 *
 * @param Client $client              Guzzle client instance.
 * @param string $apiUrl              Authorized API base URL.
 * @param string $authToken           Authorization token for requests.
 * @param string $bucketId            ID of the bucket to scan.
 * @param string $prefix              Folder prefix to filter (can be empty for root).
 * @param bool   $verbose             Whether to print each file found.
 * @param bool   $includeVersions     Whether to include file versions and deletions.
 * @return array                      Array of folders with total size and file count.
 */
function scanAllFiles($client, $apiUrl, $authToken, $bucketId, $prefix, $verbose, $includeVersions) {
    $folders = [];
    $startFileName = null;
    $startFileId = null;
    $endpoint = $includeVersions ? '/b2api/v2/b2_list_file_versions' : '/b2api/v2/b2_list_file_names';

    do {
        $body = [
            'bucketId' => $bucketId,
            'prefix' => $prefix,
            'maxFileCount' => 1000,
        ];
        if ($startFileName) $body['startFileName'] = $startFileName;
        if ($startFileId && $includeVersions) $body['startFileId'] = $startFileId;

        $response = $client->request('POST', $apiUrl . $endpoint, [
            'headers' => ['Authorization' => $authToken],
            'json' => $body,
        ]);

        $data = json_decode($response->getBody(), true);
        foreach ($data['files'] as $file) {
            if (str_ends_with($file['fileName'], '/')) continue;
            if (isset($file['action']) && $file['action'] === 'delete') continue;

            $size = $file['contentLength'] ?? $file['size'] ?? 0;
            $path = $file['fileName'];
            $folder = strpos($path, '/') !== false ? substr($path, 0, strrpos($path, '/') + 1) : '';

            if (!isset($folders[$folder])) {
                $folders[$folder] = ['folder' => $folder, 'size_bytes' => 0, 'file_count' => 0];
            }
            $folders[$folder]['size_bytes'] += $size;
            $folders[$folder]['file_count']++;

            if ($verbose) echo "  {$file['fileName']} ({$size} bytes)\n";
        }

        $startFileName = $data['nextFileName'] ?? null;
        $startFileId = $data['nextFileId'] ?? null;
    } while ($startFileName);

    return array_values($folders);
}

// Main execution
$startTime = microtime(true);
$results = scanAllFiles($client, $apiUrl, $authToken, $bucketId, $prefixArg, $verbose, $includeVersions);
$totalSize = array_sum(array_column($results, 'size_bytes'));
$totalCount = array_sum(array_column($results, 'file_count'));
$elapsed = microtime(true) - $startTime;

// Output results
if ($output === 'json') {
    echo json_encode([
        'bucket' => $bucketName,
        'prefix' => $prefixArg,
        'folders' => $results,
        'total_files' => $totalCount,
        'total_size_bytes' => $totalSize,
        'elapsed_seconds' => round($elapsed, 2),
    ], JSON_PRETTY_PRINT) . "\n";
} elseif ($output === 'csv') {
    echo "folder,size_bytes,file_count\n";
    foreach ($results as $row) {
        echo "{$row['folder']},{$row['size_bytes']},{$row['file_count']}\n";
    }
} else {
    foreach ($results as $row) {
        echo str_pad($row['folder'], 40) . formatBytes($row['size_bytes']) . " in " . number_format($row['file_count']) . " files\n";
    }
    echo "\nSummary\n";
    echo "Total folders: " . count($results) . "\n";
    echo "Total files:   " . number_format($totalCount) . "\n";
    echo "Total size:    " . formatBytes($totalSize) . "\n";
    echo "Elapsed time:  " . round($elapsed, 2) . " seconds\n";
}

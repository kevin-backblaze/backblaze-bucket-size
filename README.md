# Backblaze B2 Bucket Size Analyzer

A PHP CLI utility for scanning and reporting total file size and file count in Backblaze B2 buckets using the B2 Native API. Supports output in text, JSON, or CSV formats and provides options for scanning versions and deletions.

## Description

This tool helps developers and cloud administrators quickly audit and measure storage usage across B2 buckets or within a specific folder prefix. It includes optional verbosity, output formatting, and file version tracking.

The script leverages the Backblaze B2 Native API via Guzzle, and is well-suited for performance testing, billing audits, and capacity planning.

## Getting Started

### Dependencies

- PHP 8.0+
- [Composer](https://getcomposer.org/)
- GuzzleHTTP (installed via Composer)
- A `.env` file containing your B2 `accountId` and `applicationKey`

### Installing

1. Clone the repository:

```bash
git clone https://github.com/kevin-backblaze/backblaze-bucket-size.git
cd backblaze-bucket-size
```

2. Install Guzzle:

```bash
composer install
```

3. Create a `.env` file one directory above the script and name it appropriately (e.g., `eu-master.env`):

```
B2_ACCOUNT_ID=your-account-id
B2_APPLICATION_KEY=your-application-key
```

4. Load the environment variables into your shell:

```bash
source ../eu-master.env
```

### Executing program

Run the script using:

```bash
php b2-bucket-size.php <bucket-name> [folder-prefix/] [--verbose] [--output=text|json|csv] [--versions=true|false]
```

#### Examples:

```bash
# Scan entire bucket and show results in text format
php b2-bucket-size.php my-bucket

# Scan specific folder with verbose file listing
php b2-bucket-size.php my-bucket folder1/ --verbose

# Output as JSON
php b2-bucket-size.php my-bucket --output=json

# Disable version and deletion tracking
php b2-bucket-size.php my-bucket --versions=false
```

## Help

- Ensure your `.env` file is correctly loaded using `source`
- If file sizes show as 0, check that your key has permission to read file metadata
- If you get "Bucket not found", verify the bucket name and credentials

To inspect the command-line options:

```bash
php b2-bucket-size.php
```

## Authors

**Kevin Lott**  
[GitHub Profile](https://github.com/kevin-backblaze)

## Version History


### 0.1
- Initial release with B2 Native API

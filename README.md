# easytoyou-api

A simple API and CLI tool to automate decoding ionCube files using [EasyToYou.eu](https://easytoyou.eu). No need to manually upload and download files—this project does it for you!

## Features

- Decode single files or entire directories
- Supports both demo and premium decoders (multiple PHP versions)
- Handles login, file upload, and download automatically
- Stores decode results in a manifest file
- Optional async download and directory watch mode

## Requirements

- PHP 7.4+
- Composer

## Installation

```sh
composer install
```

## Usage

### CLI

The main entry point is `bin/ety-decoder`.

#### Decode a single file

```
bin/ety-decoder \
	decode-file \
	--src /path/to/encoded.php \
	--dest /path/to/decoded.php \
	[--username youruser --password yourpass]
```

#### Decode a directory

```
bin/ety-decoder \
	decode-dir \
	--src /path/to/encoded-dir \
	--dest /path/to/decoded-dir \
	[--chunk-size 5] \
	[--username youruser --password yourpass] \
	[--async-dl] \
	[--watch]
```

- `--chunk-size`: Number of files to send per request (default: 5)
- `--async-dl`: Download decoded files asynchronously
- `--watch`: Watch for new files in the source directory

#### Options

- `--username`, `--password`: Your EasyToYou.eu account credentials (for premium decoding)
- `--decoder`: Specify decoder class (e.g., for different PHP versions)
- `--manifest-file`: Path to store decode results (JSON)

#### Environment Variables

You can set your credentials using environment variables:

- `ETY_USERNAME`: EasyToYou.eu username
- `ETY_PASSWORD`: EasyToYou.eu password
- `ETY_PROXY`: Proxy server (optional)

## Account Country Restriction

When you create an account on EasyToYou.eu, it is tied to the country of your IP address at registration. You can only use the account from IPs in that country (e.g., UAE account only works with UAE IPs). This restriction prevents sharing accounts with others and is enforced by EasyToYou.eu.

## API Usage

You can use the API class in your PHP code:

```php
use Hosni\EasytoyouApi\API;
use Hosni\EasytoyouApi\HttpClient;

$client = HttpClient::make();
$api = new API($client);
$result = $api->decode(new \SplFileInfo('/path/to/encoded.php'));
```

## Notes

- Demo decoders only decode up to 30 lines.
- Premium decoders require an account and membership.
- Manifest files store decode results for auditing.

## Supported Decoders

- Demo and premium decoders for PHP 5.3, 5.4, 5.5, 5.6, 7.0, 7.1, 7.2, 7.4

## License

MIT

<?php

require __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$s3 = new S3Client([
    'version'     => 'latest',
    'region'      => 'us-east-1', // Scality usually ignores region but SDK requires one
    'endpoint'    => 'http://s3.asean.scality.io',
    'use_path_style_endpoint' => true, // REQUIRED for Scality
    'credentials' => [
        'key'    => 'YN1CHSX2K9WTCHHJ1LS7',
        'secret' => 'kYkD+o3B=r1bUHkwqr7Rae9Q2msTodiBc8Q/AoSK',
    ],
    'http' => [
        'verify' => false, // endpoint is HTTP, not HTTPS
    ],
]);

$bucket = 'novupay';
$key    = 'novupay/test/scality-test.txt';

try {
    echo "Uploading file...\n";

    $s3->putObject([
        'Bucket' => $bucket,
        'Key'    => $key,
        'Body'   => 'NovuPay Scality S3 integration test',
        'ACL'    => 'private',
        'ContentType' => 'text/plain',
    ]);

    echo "Upload OK\n";

    echo "Downloading file...\n";

    $result = $s3->getObject([
        'Bucket' => $bucket,
        'Key'    => $key,
    ]);

    echo "Downloaded content:\n";
    echo (string) $result['Body'] . "\n";

    echo "Generating pre-signed URL...\n";

    $cmd = $s3->getCommand('GetObject', [
        'Bucket' => $bucket,
        'Key'    => $key,
    ]);

    $request = $s3->createPresignedRequest($cmd, '+10 minutes');

    echo "Pre-signed URL:\n";
    echo (string) $request->getUri() . "\n";

} catch (AwsException $e) {
    echo "S3 ERROR:\n";
    echo $e->getAwsErrorMessage() ?: $e->getMessage();
    echo "\n";
}

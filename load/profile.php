<?php

/**
 * Times each kind of page in-process (warm, N repetitions): total milliseconds, number of SQL queries and time spent
 * in the database. Run with the same environment as the app, e.g. through load/local-stack:
 *
 *   php load/profile.php [repetitions]
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$n = (int) ($argv[1] ?? 40);

$handle = function (string $uri, array $cookies = []) use ($kernel, $app) {
    $request = Request::create($uri, 'GET', [], $cookies);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};

// Discover real URLs from the data.
$handle('/'); // boot once
$html = $handle('/gallery')->getContent();
preg_match('#/manicures/[0-9a-f-]{36}#', $html, $m);
$manicure = $m[0];
$brandHtml = $handle('/catalog/brand-1')->getContent();
preg_match('#href="(/brand-1/[0-9a-f-]{36}/[a-z0-9-]+)"#', $brandHtml, $m);
$polish = $m[1];

$pages = ['/gallery' => 'gallery', '/gallery?sort=loved' => 'gallery (most hearted)', '/gallery?brand=brand+7' => 'gallery (brand filter)',
    $polish => 'polish page', '/catalog/brand-1' => 'catalog brand page', '/catalog' => 'catalog index', $manicure => 'manicure page'];

printf("%-26s %8s %9s %10s %12s\n", 'page', 'ms/req', 'queries', 'sql ms', 'peak MB');
$queries = 0;
$sql = 0.0;
DB::listen(function ($q) use (&$queries, &$sql) {
    $queries++;
    $sql += $q->time;
});
foreach ($pages as $uri => $label) {
    $handle($uri); // warm
    $queries = 0;
    $sql = 0.0;
    $t = microtime(true);
    for ($i = 0; $i < $n; $i++) {
        $handle($uri);
    }
    $ms = (microtime(true) - $t) * 1000 / $n;
    printf("%-26s %8.1f %9.1f %10.1f %12.0f\n", $label, $ms, $queries / $n, $sql / $n, memory_get_peak_usage(true) / 1048576);
}

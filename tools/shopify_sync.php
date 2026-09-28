<?php
/**
 * Pulls Shopify products, prices and stock into the catalogue.
 *
 *     php tools/shopify_sync.php
 *
 * Meant for cron. Once a night is plenty for a catalogue; hourly is fine too:
 *
 *     17 3 * * *  cd /home/USER/catalogue && /usr/local/bin/php tools/shopify_sync.php >> logs/shopify.log 2>&1
 *
 * Exit code 0 on success, 1 on failure, so a cron that mails on failure only
 * stays quiet when things are working.
 *
 * CLI only - opening it in a browser does nothing.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script only runs from the command line.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

$started = microtime(true);
$report  = ShopifySync::run();

printf("[%s] %s\n",
    date('Y-m-d H:i:s'),
    $report['message']);

if ($report['ok']) {
    printf("           fetched=%d cached=%d removed=%d calls=%d in %.1fs\n",
        $report['fetched'], $report['updated'], $report['removed'],
        $report['calls'], microtime(true) - $started);
    exit(0);
}

// Written to the PHP error log as well as stdout, because a cron whose output
// goes nowhere is the usual reason a sync is found to have been broken for
// three weeks.
error_log('[catalogue] Shopify sync failed: ' . $report['message']);
exit(1);

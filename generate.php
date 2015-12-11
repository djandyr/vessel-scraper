<?php

use League\Csv\Writer;
require_once __DIR__ . '/vendor/autoload.php';

$start = microtime(true);

if (!ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

error_reporting(E_ALL);
ini_set('display_errors', true);
set_time_limit(0);

$count = 0;

$types = array(
    0 => 'Reserved',
    1 => 'Navigation Aid',
    2 => 'Fishing',
    3 => 'Motor Hopper',
    4 => 'High Speed Craft',
    5 => 'Unknown',
    6 => 'Passenger',
    7 => 'Cargo',
    8 => 'Tanker',
    9 => 'Pleasure Craft'
);

$useragents = file(dirname(__FILE__) . '/useragents.txt');

$cli = new Goutte\Client();

$guzzle = $cli->getClient();
$cli->setClient($guzzle);

$url = "http://www.marinetraffic.com/en/ais/index/ships/all/per_page:50";
$filename = realpath(dirname(__FILE__)) . '/vessel.csv';
$csv = Writer::createFromPath(new SplFileObject($filename, 'w+'), 'w');
$csv->insertOne(["imo", "name", "flag", "type"]);

/**
 * Crawl through vessel pages and extract information
 */

try {

    $cli->setHeader('User-Agent', getRandomUserAgent());
    $crawler = $cli->request('GET', $url);
    $totalPages = $crawler->filter('#result_page')->extract(array('max'))[0];
    $progressBar = new \ProgressBar\Manager(0, $totalPages);
    $codeCount = 0;

    for ($x = 1; $x <= $totalPages; $x++) {
        parsePage($x, $cli, $url, $csv, $count, $progressBar);

        if($codeCount == 0) {
            parsePage($x, $cli, $url, $csv, $count, $progressBar);
        }
    }

} catch (\Exception $e) {
    echo "Unable to parse HTML " . $url;
    die();
}

function parsePage($x, $cli, $url, $csv, $count, $progressBar) {

    // Total number of IMO codes per page, to identify if we have reached the end of the results
    // which have valid codes.
    $codeCount = 0;

    $cli->setHeader('User-Agent', getRandomUserAgent());
    $crawler = $cli->request('GET', $url . "/sort:IMO/direction:desc/status:active/page:{$x}");

    // Change proxy every 50 requests to prevent blocking
    if ($x % (50 + 1) == 0) {
        $crawler = $cli->request('GET', $url . "/sort:IMO/direction:desc/status:active/page:{$x}");
    }

    $crawler->filter('.filters_results_table > .mt-table table tr')->each(function ($tr) use ($csv, &$count, $x, $codeCount) {

        global $codeCount;
        $rows = array();

        if($tr->count()) {
            if (imoCode($tr->filter('td'))) {
                $rows[] = createRow($tr->filter('td'));
                $csv->insertAll($rows);
                $count += 1;
                $codeCount +=1;
            }
        }

    });

    $progressBar->update($x);
}

$duration = microtime(true) - $start;
$memory = memory_get_peak_usage(true);
echo 'Added ' . $count . ' vessels in ' . $duration . ' seconds using ' . $memory, ' bytes' . PHP_EOL;
echo 'Created ' . $filename . PHP_EOL;
//outputVesselTypes();

/**
 * Create vessel row
 *
 * @param $node
 * @return array
 */
function createRow($node)
{
    return array(
        'imo' => imoCode($node),
        'name' => vesselName($node),
        'flag' => flagCode($node),
        'type' => vesselType($node)
    );
}

/**
 * Parse IMO code
 *
 * Validate IMO is 7 digits long, and numeric
 *
 * @param $node
 * @return string
 */
function imoCode($node)
{
    if ($node->eq(1)->count()) {
        $code = trim($node->eq(1)->text());
        if(preg_match("/(?<=IMO: )(\d*)/", $code, $match)) {
            if (is_numeric($match[0]) && strlen($match[0]) == 7) {
                return $match[0];
            }
        }
    }

    return null;
}

/**
 * Parse Vessel Name
 *
 * @param $node
 * @return string
 */
function vesselName($node)
{
    if ($node->eq(3)->count()) {
        return trim($node->eq(3)->text());
    }

    return null;
}

/**
 * Parse Flag Node
 *
 * @param $node
 * @return string
 */
function flagCode($node)
{
    if ($node->eq(0)->count()) {
        if ($node->eq(0)->filter('span img')->count()) {
            return chop(basename($node->eq(0)->filter('span img')->attr('src')), '.png');
        }
    }

    return null;
}

/**
 * Parse Vessel Type
 *
 * @param $node
 * @return int
 */
function vesselType($node)
{
    global $types;

    if ($node->eq(5)->count()) {
        if ($node->eq(5)->filter('img')->count()) {

            $id = (int)filter_var(basename($node->eq(5)->filter('img')->attr('src')), FILTER_SANITIZE_NUMBER_INT);
            $name = trim($node->eq(5)->filter('img')->attr('title'));

            if (!isset($types[$id])) {
                $types[$id] = $name;
            }

            return $types[$id];
        }
    }

    return null;
}

/**
 * Output Vessel type ID and name
 */
function outputVesselTypes()
{
    global $types;

    ksort($types);

    foreach ($types as $k => $v) {
        echo sprintf('%d:%s', $k, $v) . PHP_EOL;
    }
}

/**
 * Get random user agent string
 */
function getRandomUserAgent() {
    global $useragents;
    return $useragents[rand(0, count($useragents) - 1)];
}

/**
 * Get random proxy
 *
 * @return array
 */
function getRandomProxy() {

    global $proxies;
    list($ip, $port) = explode(':', trim($proxies[array_rand($proxies)]));

    if($fp = @fsockopen($ip, $port, $errno, $errstr, 10)) {
        return 'http://' . $ip . ':' . $port;
    } else {
        return getRandomProxy();
    }
}
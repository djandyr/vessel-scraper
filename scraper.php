<?php

namespace VesselScraper;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/crawler/marinetraffic.php';
require_once __DIR__ . '/crawler/myshiptracking.php';

use League\Csv\Writer;
use GuzzleHttp\Client as GuzzleClient;
use VesselScraper\Crawler\CrawlerAbstract;
use VesselScraper\Crawler\MyShipTracking;

if (!ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

error_reporting(-1);
ini_set('display_errors', true);
set_time_limit(0);

class VesselScraper
{
    protected $csv;
    protected $progressBar;
    protected $stats = array(
        'count'     => 0,
        'start'     => 0,
        'duration'  => 0,
        'memory'    => 0
    );

    protected $proxy = '';
    protected $config = array(
        'enable-proxy' => false
    );

    public function __construct()
    {
        $this->stats['start'] = microtime(true);

        $options = array(
            'defaults' =>
                array(
                    'allow_redirects' => false,
                    'cookies' => true
                )
        );

        if(isset($this->proxy) && $this->config['enable-proxy'] === true) {
            $options['config'] = array(
                'curl' => array(
                    'CURLOPT_PROXY' => $this->proxy,
                    'CURLOPT_HTTPPROXYTUNNEL' => 1,
                )
            );
        }

        $this->client = new GuzzleClient($options);

        $this->csvFileName = realpath(dirname(__FILE__)) . '/vessel.csv';
        $this->csv = $this->buildCsv();
    }

    public function run() {

        try {
            $this->crawler = new MyShipTracking($this->client);
            $totalPages = $this->crawler->totalPages();

            $this->progressBar = new \ProgressBar\Manager(0, $totalPages);

            $pool = new \GuzzleHttp\Pool($this->client, $this->crawler->doRequests($totalPages), [
                'concurrency' => 40,
                'fulfilled' => function ($response, $index) {
                    $html = $response->getBody()->getContents();
                    if(!empty($html)) {
                        $rows = $this->crawler->filter($html);
                        $this->addToCsv($rows);
                        $this->progressBar->update($index);
                    }
                    unset($html);
                    unset($rows);
                },
                'rejected' => function ($reason, $index) {
                    echo $reason->getMessage();
                },
            ]);

            $promise = $pool->promise();
            $promise->wait();

        } catch (\Exception $e) {
            $response = $e->getMessage();
            echo $response;
        }

        $this->stats['duration'] = microtime(true) - $this->stats['start'];
        $this->stats['memory'] = memory_get_peak_usage(true);

        $this->outputStats();
    }

    /**
     * @param $rows
     */
    protected function addToCsv($rows) {
        if(count($rows) > 0) {
            $this->csv->insertAll($rows);
        }
    }

    /**
     *
     * @return static
     */
    protected function buildCsv()
    {
        $filePath = realpath(dirname(__FILE__)) . '/vessel.csv';
        $csv = Writer::createFromPath(new \SplFileObject($filePath, 'w+'), 'w');
        $csv->insertOne(["imo", "eni", "mmsi", "name", "flag", "type"]);
        return $csv;
    }

    /**
     *  Output Stats
     */
    protected function outputStats() {
        echo 'Added ' . $this->stats['count'] . ' vessels in ' . $this->stats['duration'] . ' seconds using ' . $this->stats['memory'], ' bytes' . PHP_EOL;
        echo 'Created ' . $this->csvFileName . PHP_EOL;
    }
}

$vesselScraper = new VesselScraper();
$vesselScraper->run();
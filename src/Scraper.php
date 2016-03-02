<?php

namespace WarrantGroup\VesselScraper;

use GuzzleHttp\Client as GuzzleClient;
use WarrantGroup\VesselScraper\Crawler\CrawlerAbstract;
use WarrantGroup\VesselScraper\Crawler\MarineTraffic;
use WarrantGroup\VesselScraper\Crawler\MyShipTracking;
use WarrantGroup\VesselScraper\Csv\Writer;

error_reporting(-1);
ini_set('display_errors', true);
set_time_limit(0);

class Scraper
{
    protected $csv;
    protected $progressBar;

    protected $proxy = '';
    protected $config = array(
        'enable-proxy' => false
    );

    public function __construct($name)
    {
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
        $this->crawler = $this->createCrawler($name);
    }

    public function run() {

        try {
            $this->csv = new Writer();
            $totalPages = $this->crawler->totalPages();

            $this->progressBar = new \ProgressBar\Manager(0, $totalPages);

            $pool = new \GuzzleHttp\Pool($this->client, $this->crawler->doRequests($totalPages), [
                'concurrency' => 40,
                'fulfilled' => function ($response, $index) {

                    $html = $response->getBody()->getContents();

                    if(!empty($html)) {
                        $rows = $this->crawler->filter($html);
                        $this->csv->write($rows);
                        $this->progressBar->update($index);
                    }

                    unset($response);
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
    }

    public function getCsv() {
        return $this->csv;
    }

    /**
     * Factory
     *
     * @param $name
     * @return MarineTraffic|MyShipTracking
     */
    protected function createCrawler($name) {

        switch($name) {
            case 'marinetraffic' :
                return new MarineTraffic($this->client);
                break;

            case 'myshiptracking' :
            default:
                return new MyShipTracking($this->client);
                break;
        }
    }
}
<?php

namespace WarrantGroup\VesselScraper\Crawler;

use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request;
use WarrantGroup\VesselScraper\Crawler\CrawlerAbstract;

class MyShipTracking extends CrawlerAbstract {

    protected $url = "http://www.myshiptracking.com/search/vessels?sort_dir=DESC&sort=MMSI&pp=50";
    protected $client;

    protected $stats = array(
        'count'     => 0,
        'start'     => 0,
        'duration'  => 0,
        'memory'    => 0
    );

    public function __construct($client)
    {
        parent::__construct();
        $this->client = $client;
    }

    /**
     * Total number of pages to crawl
     *
     * @return mixed
     */
    public function totalPages() {

        $response = $this->client->request('GET', $this->url, array(
            'headers' => array(
                'User-Agent' => $this->getRandomUserAgent()
            )
        ));

        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);
        $total = $crawler->filter('#content_in > div > div.listbox.anc_activity.ads_160_right > div > div > div > div.paging_column_center.center > nav > ul > li:nth-child(7) > a')->text();

        return $total;
    }

    /**
     * Fire Requests
     *
     * @param $total
     * @param $url
     * @return Generator
     */
    public function doRequests ($total)
    {
        for ($i = 0; $i < $total; $i++) {
            yield new \GuzzleHttp\Psr7\Request('GET', $this->url . "&page={$i}", array(
                'User-Agent' => $this->getRandomUserAgent()
            ));
        }
    }

    /**
     * Filter vessel codes
     *
     * @param $node
     * @return array
     */
    public function filter($html)
    {
        return $this->filterTable('#content_in > div > div.listbox.anc_activity.ads_160_right > div > table tbody tr', $html);
    }


    /**
     * Parse IMO code
     *
     * Validate IMO is 7 digits long, and numeric
     *
     * @param $node
     * @return string
     */
    protected function imoCode($node)
    {
        if ($node->eq(3)->count()) {
            $code = trim($node->eq(3)->text());
            if (is_numeric($code) && strlen($code) == 7) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Parse MMSI
     *
     * A Maritime Mobile Service Identity (MMSI) is a series of nine digits which are sent in digital
     * form over a radio frequency channel in order to uniquely identify ship stations,
     * ship earth stations, coast stations, coast earth stations, and group calls.
     *
     * @param $node
     */
    protected function mmsi($node) {
        if ($node->eq(2)->count()) {
            return trim($node->eq(2)->text());
        }

        return null;
    }

    /**
     * Parse Vessel Name
     *
     * @param $node
     * @return string
     */
    protected function vesselName($node)
    {
        if ($node->eq(1)->count()) {
            if($node->eq(1)->filter('span.table_title')->count()) {
                return trim($node->eq(1)->filter('span.table_title')->text());
            }
        }

        return null;
    }

    /**
     * Parse Flag Node
     *
     * @param $node
     * @return string
     */
    protected function flagCode($node)
    {
        if ($node->eq(0)->count()) {
            if ($node->eq(0)->filter('img')->count()) {

                $flag = chop(basename($node->eq(0)->filter('img')->attr('src')), '.png');

                if(ctype_alpha($flag)) {
                    return $flag;
                }
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
    protected function vesselType($node)
    {
        if ($node->eq(1)->count()) {
            if($node->eq(1)->filter('span.small11')->count()) {
                return trim($node->eq(1)->filter('span.small11')->text());
            }
        }

        return null;
    }
}
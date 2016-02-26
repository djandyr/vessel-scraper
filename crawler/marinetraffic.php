<?php

namespace VesselScraper\Crawler;

require_once __DIR__ . '/abstract.php';

use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request;

class MarineTraffic extends CrawlerAbstract {

    protected $url = "http://www.marinetraffic.com/en/ais/index/ships/all/per_page:50";
    protected $client;

    protected $stats = array(
        'count'     => 0,
        'start'     => 0,
        'duration'  => 0,
        'memory'    => 0
    );

    protected $types = array(
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
        $total = $crawler->filter('#result_page')->extract(array('max'))[0];

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
            yield new \GuzzleHttp\Psr7\Request('GET', $this->url . "/sort:IMO/direction:desc/status:active/page:{$i}", array(
                'User-Agent' => $this->getRandomUserAgent()
            ));
        }
    }

    /**
     * Crawl vessel codes
     *
     * @param $node
     * @return array
     */
    public function filter($html)
    {
        return $this->filterTable('.filters_results_table > .mt-table table tr', $html);
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
     * Parse ENI number
     *
     * European Vessel Identification Number) is a registration for ships capable of navigating on inland
     * European waters. It is a unique, eight-digit identifier that is attached to a hull for its entire lifetime,
     * independent of the vessel's current name or flag.
     *
     * @param $node
     * @return string
     */
    protected function eniNumber($node)
    {
        if ($node->eq(1)->count()) {
            $code = trim($node->eq(1)->text());
            if(preg_match("/(?<=ENI: )(\d*)/", $code, $match)) {
                if (is_numeric($match[0]) && strlen($match[0]) == 8) {
                    return $match[0];
                }
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
    protected function flagCode($node)
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
    protected function vesselType($node)
    {
        if ($node->eq(5)->count()) {
            if ($node->eq(5)->filter('img')->count()) {

                $id = (int)filter_var(basename($node->eq(5)->filter('img')->attr('src')), FILTER_SANITIZE_NUMBER_INT);
                $name = trim($node->eq(5)->filter('img')->attr('title'));

                if (!isset($this->types[$id])) {
                    $this->types[$id] = $name;
                }

                return $this->types[$id];
            }
        }

        return null;
    }
}
<?php

namespace WarrantGroup\VesselScraper\Crawler;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Class CrawlerAbstract
 */
abstract class CrawlerAbstract {

    public function __construct() {
        $this->useragents = file(dirname(__FILE__) . '/useragents.txt');
    }

    /**
     * Filter table HTML into row array, only accepts vessel information
     * which has a valid IMO code
     *
     * @param $selector
     * @param $html
     * @return array
     */
    protected function filterTable($selector, $html) {

        $crawler = new Crawler($html);
        $rows = array();

        $crawler->filter($selector)->each(function ($tr) use (&$rows) {
            if($tr->count()) {
                if ($this->imoCode($tr->filter('td'))) {
                    $rows[] = $this->createRow($tr->filter('td'));
                }
            }
        });

        return $rows;
    }

    /**
     * Get Random User Agent
     *
     * @return mixed
     */
    protected function getRandomUserAgent() {
        return $this->useragents[rand(0, count($this->useragents) - 1)];
    }

    /**
     * Create Row
     *
     * @param $node
     * @return array
     */
    protected function createRow($node) {
        return array(
            'imo' => $this->imoCode($node),
            'mmsi' => $this->mmsi($node),
            'name' => $this->vesselName($node),
            'flag' => $this->flagCode($node),
            'type' => $this->vesselType($node)
        );
    }


    abstract protected function totalPages();
    abstract protected function doRequests($total);
    abstract protected function imoCode($node);
    abstract protected function mmsi($node);
    abstract protected function vesselName($node);
    abstract protected function flagCode($node);
    abstract protected function vesselType($node);

}
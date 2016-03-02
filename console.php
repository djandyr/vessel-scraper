<?php

require_once('vendor/autoload.php');

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WarrantGroup\VesselScraper\Scraper;

$console = new Application();

$console
    ->register('scrape:vessels')
    ->setDefinition(array(
        new InputArgument('type', InputArgument::OPTIONAL, 'Which type of scraper to use?', 'myshiptracking'),
    ))
    ->setDescription('Crawl different marine websites and scrape vessel information (IMO, MMSI, Vessel Name, Flag Code and Vessel Type)')
    ->setCode(function (InputInterface $input, OutputInterface $output) {

        $scraper = new Scraper($input->getArgument('type'));
        $scraper->run();

        $output->writeln('<info>Created' . $scraper->getCsv()->getFilePath() . '</info>');
    });

$console->run();
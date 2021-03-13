<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeHelper
{
    public static function fetchDocument(string $url): Crawler
    {
        $client = new Client();

        try {
            $response = $client->get($url);
        } catch (GuzzleException $e) {
            die($e->getMessage());
        }

        return new Crawler($response->getBody()->getContents(), $url);
    }
}

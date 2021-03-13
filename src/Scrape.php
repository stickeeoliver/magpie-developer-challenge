<?php

namespace App;

use DateTime;
use Exception;
use Symfony\Component\DomCrawler\Crawler;

class Scrape
{
    const BASE_URL = 'https://www.magpiehq.com/developer-challenge/smartphones';

    private array $products = [];
    private array $pages = [];

    public function run(): void
    {
        $document = ScrapeHelper::fetchDocument(self::BASE_URL);

        $this->scrape($document);

        file_put_contents('output.json', json_encode($this->products));
    }

    public function scrape(Crawler $document, bool $deep = false): void
    {
        $this->products = $document->filter('.product')->each(function (Crawler $node) {
            $product = new Product();

            // Avoiding repeat products in the array by checking their titles
            $title = $node->filter('.product-name')->first();
            if (in_array($title->text(), array_map(function (Product $product) {
                return $product->title;
            }, $this->products))) {
                return null;
            }

            $product->title = $title->text();

            $product->capacityMb = $this->capacityStringToMb($title->siblings()
                ->filter('.product-capacity')
                ->text());

            $product->imageUrl = self::BASE_URL . $this->cleanUrl(
                $node
                    ->filter('img')
                    ->attr('src')
            );

            $product->colour = $node->filter('.my-4')
                ->filter('.px-2')
                ->filter('span')
                ->each(function (Crawler $node) {
                    return $node->attr('data-colour');
                });

            $price = $node->filter('.my-8')->filter('.block');
            $product->price = floatval(ltrim($price->text(), '£'));

            // Things get a little complicated here as DOM elements are sometimes missing with no identifiers
            $node->filter('.bg-white')
                ->children()
                ->filter('.my-4')
                ->filter('.text-sm')
                ->each(function (Crawler $crawler) use ($product) {
                    $node = $crawler->text();

                    if (str_contains(strtolower($node), 'availability')) {
                        $product->availabilityText = $this->trimAvailability($node);
                        $product->isAvailable = true;
                        if (str_contains(strtolower($product->availabilityText), 'out of')) {
                            $product->isAvailable = false;
                        }
                    } else {
                        $product->shippingText = $node;
                        $product->shippingDate = $this->trimUntilValidDate($node);
                    }
                });

            return $product;
        });

        // Check to see whether we need to recurse here
        if (!$deep) {
            $pagination = $document->filter('#pages')->children();

            $pagination->children()->each(function (Crawler $link) {
                $href = self::BASE_URL . '?page=' . $link->text();
                $active = $link->filter('.active');

                if (count($active)) {
                    $this->pages[] = $href;
                } elseif (!in_array($href, $this->pages)) {
                    /**
                     * I've tried to do this recursively but just run into problems, so instead
                     * I'm basically just using a poltergeist to get what I want.
                     */
                    $scrape = new Scrape();
                    $scrape->scrape(ScrapeHelper::fetchDocument($href), true);
                    $this->products = array_merge($this->products, $scrape->products);
                }
            });
        }

        // Remove any null values that might've accumulated from repeat products
        $this->products = array_filter($this->products);
    }

    /**
     * Takes a product capacity string, like "128MB" or "128 GB" and returns an integer
     * value of the size capacity in megabytes, converting if necessary.
     *
     * @param string $capacity
     * @return int
     */
    protected function capacityStringToMb(string $capacity): int
    {
        $value = strval(preg_replace("/[^0-9]/", '', $capacity));

        if (str_contains(strtolower($capacity), 'mb')) {
            return $value;
        }

        return $value * 1024;
    }

    /**
     * Removes full stops from the beginning of strings.
     *
     * @param string $url
     * @return string
     */
    protected function cleanUrl(string $url): string
    {
        return ltrim($url, '.');
    }

    /**
     * Trims "Availability:" from the beginning of a string.
     *
     * @param string $availability
     * @return string
     */
    protected function trimAvailability(string $availability): string
    {
        return trim(str_ireplace('availability:', '', trim($availability)));
    }

    /**
     * Attempts to find a valid date substring within a string and returns a
     * DateTime object if it does.
     *
     * @param string $shippingText
     * @return null|DateTime
     */
    protected function trimUntilValidDate(string $shippingText): ?DateTime
    {
        $time = strtotime($shippingText);

        while (!$time) {
            $shippingText = substr($shippingText, 1);

            if (strlen($shippingText) <= 1) {
                return null;
            }

            $time = strtotime($shippingText);
        }

        try {
            return new DateTime('@' . strtotime($shippingText));
        } catch (Exception $exception) {
            return null;
        }
    }
}

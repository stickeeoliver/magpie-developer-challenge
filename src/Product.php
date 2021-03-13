<?php

namespace App;

use DateTime;

class Product
{
    public string $title;
    public float $price;
    public string $imageUrl;
    public int $capacityMb;
    public array $colour;
    public string $availabilityText;
    public bool $isAvailable;
    public string $shippingText;
    public ?DateTime $shippingDate;
}

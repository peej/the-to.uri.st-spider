<?php

define('GEOCODER', 'yahoo');

require_once 'spider.php';

$data = new Data('www.worldtravelguide.net', 'Peej');

$indexUrl = new URL('http://www.worldtravelguide.net/attraction/');
$indexPage = $indexUrl->get();

foreach ($indexPage->match('/<li><b><a href="([^"]+)/') as $match) {
    $continentUrl = new URL($match);
    $continentPage = $continentUrl->get();
    
    foreach ($continentPage->match('/<b><a href="([^"]+)/') as $match) {
        $itemUrl = new URL($match);
        $itemPage = $itemUrl->get();
        
        list($lat, $lng) = $data->geocode(
            $itemPage->match('/<h3>Contact Addresses<\/h3>[\r\n\t ]+<div class="paragraph">.+?,([^<]+)/')
        );
        
        $data->add(array(
            'title' => preg_replace('/\([^)]+\)/', '', $itemPage->match('/<title>(.+?) Guide/')),
            'description' => $itemPage->match('/<\/h2>[\r\n\t ]+<div class="paragraph">(.+?)<\/div>/'),
            'lat' => $lat,
            'lng' => $lng
        ));
        
    }
    break;
}

$data->write();

?>

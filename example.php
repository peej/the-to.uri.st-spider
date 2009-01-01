<?php

/*
 * This example usage grabs data from www.worldtravelguide.net and creates a CSV
 * file www.worldtravelguide.net.csv containing the scraped data.
 */

// we'll just use the Yahoo geocoder
define('GEOCODER', 'yahoo');

// include the spider library
require_once 'spider.php';

// create a data object to store our data in
$data = new Data('www.worldtravelguide.net');

// get the index page of the attractions
$indexUrl = new URL('http://www.worldtravelguide.net/attraction/');
$indexPage = $indexUrl->get();

// match each subpage URL
foreach ($indexPage->match('/<li><b><a href="([^"]+)/') as $match) {
    
    // get the subpage
    $continentUrl = new URL($match);
    $continentPage = $continentUrl->get();
    
    // match each attracton page URL
    foreach ($continentPage->match('/<b><a href="([^"]+)/') as $match) {
        
        // get the attraction page
        $itemUrl = new URL($match);
        $itemPage = $itemUrl->get();
        
        // extract the address from the page and geocode it
        list($lat, $lng) = $data->geocode(
            $itemPage->match('/<h3>Contact Addresses<\/h3>[\r\n\t ]+<div class="paragraph">.+?,([^<]+)/')
        );
        
        // extract the other data from the page and add it to the data object along with the geocoded lat/lng data
        $data->add(array(
            'title' => preg_replace('/\([^)]+\)/', '', $itemPage->match('/<title>(.+?) Guide/')),
            'description' => $itemPage->match('/<\/h2>[\r\n\t ]+<div class="paragraph">(.+?)<\/div>/'),
            'lat' => $lat,
            'lng' => $lng
        ));
        
    }
}

// we're done, close the CSV file
$data->done();

?>

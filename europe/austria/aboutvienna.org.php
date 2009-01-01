<?php

define('GEOCODER', 'hybrid');

require_once '../../spider.php';

$data = new Data('aboutvienna.org');

$indexUrl = new URL('http://www.aboutvienna.org/sights/ankeruhr.htm');
$indexPage = $indexUrl->get();

foreach ($indexPage->match('/<td width="50%"(?: height="11")?><a href="([^"]+)/') as $match) {
    
    $itemUrl = new URL($match, $indexUrl);
    $itemPage = $itemUrl->get();
    
    list($lat, $lng) = $data->geocode($itemPage->match('/<span class="Adressen">([^<]+)/').' Vienna Austria');
    
    $data->add(array(
        'title' => $itemPage->match('/<h1 class="tdolive_gross_strich"><a name="1"><\/a>([^<]+)/'),
        'description' => $itemPage->match('/<\!-- google_ad_section_start -->([^<]+)/'),
        'lat' => $lat,
        'lng' => $lng,
        'href' => $itemUrl
    ));
    
}

// we're done, close the CSV file
$data->done();

?>

<?php

define('GEOCODER', 'yahoo');

require_once '../../spider.php';

$data = new Data('austria.intermaps.com');

$indexes = array(
    'museum' => 'http://austria.intermaps.com/soew/oewtemplate/mol.xml?subid=412&level=600&startX=25000&endX=700000&startY=0&endY=700000',
    'theatre' => 'http://austria.intermaps.com/soew/oewtemplate/mol.xml?subid=213&level=600&startX=25000&endX=700000&startY=0&endY=700000',
    //'sport' => 'http://austria.intermaps.com/soew/oewtemplate/mol.xml?subid=418&level=600&startX=25000&endX=700000&startY=0&endY=700000',
    'nature' => 'http://austria.intermaps.com/soew/oewtemplate/mol.xml?subid=413&level=600&startX=25000&endX=700000&startY=0&endY=700000'
);

foreach ($indexes as $type => $url) {
    
    $indexUrl = new URL($url);
    $indexPage = $indexUrl->get();
    
    foreach ($indexPage->match('/<OBJECT Object_ID="([^"]+)"/') as $match) {
        
        $itemUrl = new URL('http://austria.intermaps.com/soew/oewtemplate/ObjectShow.xml?objid='.$match.'&lang=en');
        $itemPage = $itemUrl->get();
        
        $city = $itemPage->match('/<[is]_city>([^<]+)/');
        $postcode = $itemPage->match('/<[is]_postcode>([^<]+)/');
        $location = $itemPage->match('/<[is]_location>([^<]+)/');
        
        list($lat, $lng) = $data->geocode($city.' '.$postcode.' '.$location.' Austria');
        
        $href = $itemPage->match('/<[is]_web>([^<]+)/');
        if (substr($href, 0, 7) != 'http://') {
            $href = 'http://'.$href;
        }
        
        $data->add(array(
            'title' => $itemPage->match('/<o_name>([^<]+)/'),
            'description' => $itemPage->match('/<DETAIL d_art="Beschreibung" d_language="en" d_priority="0"><\!\[CDATA\[(.+?)\]\]>/'),
            'lat' => $lat,
            'lng' => $lng,
            'href' => $href,
            'type' => $type
        ));
    }
    
}

// we're done, close the CSV file
$data->done();

?>

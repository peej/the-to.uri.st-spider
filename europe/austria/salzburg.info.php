<?php

define('GEOCODER', 'hybrid');

require_once '../../spider.php';

$data = new Data('salzburg.info');

$indexes = array(
    'http://www2.salzburg.info/sehenswertes_222.htm',
    'http://www2.salzburg.info/sehenswertes_361.htm'
);

foreach ($indexes as $url) {
    
    $indexUrl = new URL($url);
    
    while ($indexUrl) {
        $indexPage = $indexUrl->get();
        
        foreach ($indexPage->match('/<a href="([^"]+)" class="link_darkred"><b>more/') as $match) {
            
            $itemUrl = new URL($match, $indexUrl);
            $itemPage = $itemUrl->get();
            
            list($lat, $lng) = $data->geocode($itemPage->match('/<tr>[\s\n]+<td><img src="pics\/1\.gif" width="1" height="8"><\/td>[\s\n]+<\/tr>[\s\n]+<tr>[\s\n]+<td>([^<]+).*?<\/td>[\s\n]+<\/tr>[\s\n]+<tr>[\s\n]+<td><img src="pics\/1\.gif" width="1" height="8"><\/td>[\s\n]+<\/tr>/').' Salzburg Austria');
            
            $data->add(array(
                'title' => $itemPage->match('/<h1>([^<]+)/'),
                'description' => $itemPage->match('/<\/td>[\s\n]+<td valign="top">[\s\n]+<table width="160" border="0" cellspacing="0" cellpadding="0">[\s\n]+<tr>[\s\n]+<td>([^<]+)/'),
                'lat' => $lat,
                'lng' => $lng,
                'href' => $itemUrl
            ));
        }
        
        $nextUrl = $indexPage->match('/<a href="([^"]+)" class="link_darkred">next/');
        if ((string)$nextUrl) {
            $indexUrl = new URL($nextUrl, $url);
        } else {
            break;
        }
    }
}

// we're done, close the CSV file
$data->done();

?>

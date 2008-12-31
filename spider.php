<?php

if (!defined('CACHE')) define('CACHE', 'cache');
if (!defined('CHARSET')) define('CHARSET', 'ISO-8859-1');
//if (!defined('CHARSET')) define('CHARSET', 'UTF-8');
if (!defined('GEOCODER')) define('GEOCODER', 'hybrid');
if (!defined('VERBOSE')) define('VERBOSE', FALSE);

abstract class Talkie {
    public static function msg($str) {
        echo $str, "\n";
    }
}

class URL {
    private $url, $parameters;
    
    static private $cookiejar = array();
    
    public function __construct($url, $parameters = array()) {
        $this->url = $url;
        $this->parameters = $parameters;
    }
    
    public function __toString() {
        return (String)$this->url;
    }
    
    public function param($name, $value = NULL) {
        if (is_array($name)) {
            foreach ($name as $field => $value) {
                $this->parameters[$field] = $value;
            }
        } else {
            $this->parameters[$name] = $value;
        }
    }
    
    private function getQuerystring() {
        $querystring = '';
        foreach ($this->parameters as $name => $value) {
            $querystring .= '&'.urlencode($name).'='.urlencode($value);
        }
        return substr($querystring, 1);
    }
    
    public function get() {
        return $this->httpRequest('get');
        $urlString = $this->url;
        Talkie::msg('GETing '.$urlString);
        if ($this->inCache($urlString)) {
            $contents = $this->readFromCache($urlString);
        } else {
            $contents = file_get_contents($urlString);
            $content = iconv(CHARSET, 'UTF-8', $content);
            $this->writeToCache($urlString, $contents);
        }
        return new Page($urlString, $contents);
    }
    
    public function post() {
        return $this->httpRequest('post');
    }
    
    private function httpRequest($method = 'get') {
        $urlString = $this->url;
        $querystring = $this->getQuerystring();
        Talkie::msg('Requesting '.$urlString);
        if ($querystring) {
            $cacheUrlString = $urlString.'?'.$querystring;
        } else {
            $cacheUrlString = $urlString;
        }
        if ($this->inCache($cacheUrlString)) {
            $contents = $this->readFromCache($cacheUrlString);
        } else {
            $curl = curl_init();
			$this->setDefaultCurlOptions($curl);
            if ($method == 'post') {
                curl_setopt($curl, CURLOPT_POST, TRUE);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $queryString);
            }
			curl_setopt($curl, CURLOPT_URL, $urlString);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'Accept-Language' => 'en-gb,en;q=0.5'
			));
			$contents = curl_exec($curl);
			$responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);
			if (substr($responseCode, 0, 1) != '2') {
				Talkie::msg('Could not load resource ('.$responseCode.')');
				return NULL;
			}
            $contents = $this->processHTTPResponse($contents);
            $this->writeToCache($urlString, $contents);
        }
        return new Page($urlString, $contents);
    }
    
	private function setDefaultCurlOptions($curl) {
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
		curl_setopt($curl, CURLOPT_TIMEOUT, 160);
		curl_setopt($curl, CURLOPT_USERAGENT, 'to.uri.st');
		if (VERBOSE) curl_setopt($curl, CURLOPT_VERBOSE, TRUE);
		curl_setopt($curl, CURLOPT_HEADER, TRUE);
		curl_setopt($curl, CURLOPT_COOKIE, join('; ', URL::$cookiejar));
	}
    
    private function processHTTPResponse($response) {
		$parts = explode("\r\n\r\n", $response);
		$headers = array();
		foreach ($parts as $part) {
			if (substr($part, 0, 4) == 'HTTP') {
				$headers = array_merge($headers, explode("\r\n", array_shift($parts)));
			}
		}
		$this->setRequestCookie($headers);
		$content = join("\r\n\r\n", $parts);
		$content = iconv(CHARSET, 'UTF-8', $content);
		return $content;
	}
    
    private function setRequestCookie($headers) {
        foreach ($headers as $header) {
            if (strtolower(substr($header, 0, 11)) == 'set-cookie:') {
                $parts = explode(';', substr($header, 12));
                $name = explode('=', $parts[0]);
                URL::$cookiejar[$name[0]] = $parts[0];
            }
        }
	}
    
    private function inCache($url) {
        $urlHash = md5($url);
        return file_exists(CACHE.DIRECTORY_SEPARATOR.substr($urlHash, 0, 1).DIRECTORY_SEPARATOR.$urlHash);
    }
    
    private function readFromCache($url) {
        $urlHash = md5($url);
        $cacheFilename = CACHE.DIRECTORY_SEPARATOR.substr($urlHash, 0, 1).DIRECTORY_SEPARATOR.$urlHash;
        Talkie::msg('Reading cache file "'.$cacheFilename.'"');
        return file_get_contents($cacheFilename);
    }
    
    private function writeToCache($url, $contents) {
        $urlHash = md5($url);
        $cacheDirName = CACHE.DIRECTORY_SEPARATOR.substr($urlHash, 0, 1);
        if (!is_dir($cacheDirName)) mkdir($cacheDirName);
        $cacheFilename = $cacheDirName.DIRECTORY_SEPARATOR.$urlHash;
        Talkie::msg('Writing cache file "'.$cacheFilename.'"');
        file_put_contents($cacheFilename, $contents);
    }
}

class Page {
    private $url, $contents = '';
    
    public function __construct($url, $contents) {
        $this->url = $url;
        $this->contents = $contents;
    }
    
    public function __toString() {
        return (String)$this->contents;
    }
    
    public function match($regex) {
        preg_match_all($regex, $this->contents, $matches);
        return new Match($matches);
    }
}

class Match implements Iterator {
    private $matches;
    
    public function __construct($matches) {
        if (!is_array($matches)) $matches = array(NULL, array());
        $this->matches = $matches[1];
    }
    
    public function __toString() {
        return (String)$this->current();
    }
    
    public function rewind() {
        reset($this->matches);
    }

    public function current() {
        return current($this->matches);
    }

    public function key() {
        return key($this->matches);
    }

    public function next() {
        return next($this->matches);
    }
    
    public function valid() {
        return $this->current() !== FALSE;
    }
}

class Data {
    private $data, $source, $author;
    
    public function __construct($source, $author = NULL) {
        $this->data = array();
        $this->source = $source;
        $this->author = $author;
    }
    
    public function cleanData($data) {
		$bad = array('&nbsp;', "\n", "\r");
		$data = trim(preg_replace('/<[^>]+>/', ' ', str_replace($bad, ' ', $data)));
		
		$data = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec(\\1-103))', $data);
		//$data = preg_replace('~&#([0-9]+);~e', 'chr(\\1-103)', $data);
		
		$data = preg_replace('/\s+/', ' ', $data); // condense multiple whitespace
		
		$data = str_replace(
			array('&#192;', '&#193;', '&#194;', '&#195;', '&#196;', '&#197;', '&#198;', '&#199;', '&#200;', '&#201;', '&#202;', '&#203;', '&#204;', '&#205;', '&#206;', '&#207;', '&#208;', '&#209;', '&#210;', '&#211;', '&#212;', '&#213;', '&#214;', '&#215;', '&#216;', '&#217;', '&#218;', '&#219;', '&#220;', '&#221;', '&#222;', '&#223;', '&#224;', '&#225;', '&#226;', '&#227;', '&#228;', '&#229;', '&#230;', '&#231;', '&#232;', '&#233;', '&#234;', '&#235;', '&#236;', '&#237;', '&#238;', '&#239;', '&#240;', '&#241;', '&#242;', '&#243;', '&#244;', '&#245;', '&#246;', '&#247;', '&#248;', '&#249;', '&#250;', '&#251;', '&#252;', '&#253;', '&#254;', '&#255;', '&#338;', '&#339;', '&#352;', '&#353;', '&#376;', '&#402;', '&#223;'),
			array('A',      'A',      'A',      'A',      'A',      'A',      'AE',     'C',      'E',      'E',      'E',      'E',      'I',      'I',      'I',      'I',      'D',      'N',      'O',      'O',      'O',      'O',      'O',      'x',      'O',      'U',      'U',      'U',      'U',      'Y',      'p',      'B',      'a',      'a',      'a',      'a',      'a',      'a',      'ae',      'c',     'e',      'e',      'e',      'e',      'i',      'i',      'i',      'i',      'o',      'n',      'o',      'o',      'o',      'o',      'o',      '-',      'o',      'u',      'u',      'u',      'u',      'y',      'p',      'y',      'E',      'e',      'S',      's',      'Y',      'f',      'ss'),
			$data
		); // bad, but probably good enough, and the best I can come up with damn you char encoding hell
		
		$data = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
		
		return $data;
	}
    
    public function add($data) {
        if (!isset($data['title'])) {
            Talkie::msg('Can not add data, no title');
        } elseif (!isset($data['lat']) || !isset($data['lng'])) {
            Talkie::msg('Can not add data, no lat/lng');
        } else {
            
            if (!isset($data['description'])) $data['description'] = '';
            
            $data['title'] = $this->cleanData($data['title']);
            $data['description'] = $this->cleanData($data['description']);
            
            $description = $data['title'].$data['description'];
            $placeType = 'unknown';
			if (preg_match('/(shop|market)/i', $description)) {
				$placeType = 'shop';
			} elseif (preg_match('/historic/i', $description)) {
				$placeType = 'historic';
			} elseif (preg_match('/sport/i', $description)) {
				$placeType = 'sport';
			} elseif (preg_match('/(nature|park)/i', $description)) {
				$placeType = 'nature';
			} elseif (preg_match('/museum/i', $description)) {
				$placeType = 'museum';
			} elseif (preg_match('/(theatre|gallery)/i', $description)) {
				$placeType = 'theatre';
			} elseif (preg_match('/theme park/i', $description)) {
				$placeType = 'themepark';
			} elseif (preg_match('/(zoo|farm)/i', $description)) {
				$placeType = 'zoo';
			}
			
			if ($data['description']) {
				$words = explode(' ', $data['description']);
				$numberOfWords = count($words) < 40 ? count($words) : 40;
				$description = '';
				for ($foo = 0; $foo < $numberOfWords; $foo++) {
					$description .= $words[$foo].' ';
				}
				$description = substr($description, 0, -1);
				if ($foo == 39) {
					$description .= '...';
				}
				$free = 'n';
			} else {
				$description = '{{todo}}';
				$free = 'y';
			}
            
            $this->data[] = array(
                'title' => $data['title'],
                'description' => $description,
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'type' => $placeType,
                'free' => $free,
            );
            Talkie::msg('Data added "'.$data['title'].'"');
        }
    }
    
    public function geocode($address, $type = GEOCODER) {
        $address = preg_replace('/[\n\r\s]+/', ' ', trim($address));
        switch ($type) {
        case 'yahoo':
            return $this->geocodeYahoo($address);
        case 'google':
            return $this->geocodeGoogle($address);
        default:
            if (!$latlng = $this->geocodeGoogle($address)) {
                $latlng = $this->geocodeYahoo($address);
            }
            return $latlng;
        }
    }
    
    private function geocodeGoogle($address) {
        Talkie::msg('Geocoding using Google');
		$url = new URL('http://www.google.com/uds/GlocalSearch?callback=globalSearchResults&context=0&v=1.0&q='.urlencode($address));
		$contents = $url->get();
		if (preg_match('/"lat":"([0-9.-]+)","lng":"([0-9.-]+)",/', $contents, $matches)) {
			return array($matches[1], $matches[2]);
		}
	}
    
    private function geocodeYahoo($address) {
		Talkie::msg('Geocoding using Yahoo');
        $url = new URL('http://local.yahooapis.com/MapsService/V1/geocode?appid=YEoM9IbV34FN9ruRngvbeeBcyAFiwtgCwitBH32vWJIGMjCQJbf0rTwYVqezOpMy&location='.urlencode($address));
		$contents = $url->get();
        if (preg_match('/<Latitude>([0-9.-]+)<\/Latitude><Longitude>([0-9.-]+)<\/Longitude>/', $contents, $matches)) {
			return array($matches[1], $matches[2]);
		}
	}
    
    public function write($filename = NULL) {
        if (!$filename) {
            $filename = $this->source.'.csv';
        }
        Talkie::msg('Writing file "'.$filename.'"');
        if ($fp = fopen($filename, 'w')) {
            fwrite($fp, "title,description,lat,lng,type,free,source,author,created\n");
            foreach ($this->data as $row) {
                fwrite($fp, sprintf(
                    '"%s","%s","%s","%s","%s","%s","%s","%s","%s"'."\n",
                    str_replace('"', '\"', $row['title']),
                    str_replace('"', '\"', $row['description']),
                    str_replace('"', '\"', $row['lat']),
                    str_replace('"', '\"', $row['lng']),
                    str_replace('"', '\"', $row['type']),
                    str_replace('"', '\"', $row['free']),
                    str_replace('"', '\"', $this->source),
                    str_replace('"', '\"', $this->author),
                    date('Y-m-d H:i:s')
                ));
            }
            fclose($fp);
        }
    }
}

?>
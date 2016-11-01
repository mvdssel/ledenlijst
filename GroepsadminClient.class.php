<?php

require 'vendor/autoload.php';
require_once 'util.php';

/**
 * Class GroepsadminClient
 * @author Fantasierijke Saki
 */
class GroepsadminClient
{
    // Gizzle client used to perform all HTTP requests and used to retain cookies
    private $client;
    // Attribute specific to the user. Necessary to send as form attribute when setting the filter
    private $BAREBONE_ME;
    // Filters for the user
    private $filters;

    public function __construct($user, $pass) {
        // Init Guzzle client
        $this->client = new \GuzzleHttp\Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://groepsadmin.scoutsengidsenvlaanderen.be/groepsadmin/',
            // Use a shared client cookie jar for all requests
            'cookies' => true
        ]);

        // Set SESSION_ID cookie
        $this->client->request('GET');

        // Log in
        $this->login($user, $pass);
    }

    public function __destruct() {
        $this->logout();
    }

    public function downloadFilter($filter) {
        logging("Fetching filter $filter");

        $this->setFilter($filter);
        $res = $this->download();
        return $res->getBody();
    }

    public function downloadFilters($filters) {
        $result = [];
        foreach ($filters as $filter) {
            $result[$filter] = $this->downloadFilter($filter);
        }
        return $result;
    }

    private function download() {
        $res = $this->client->request('GET', 'ledenlijst.csv');
        return $res;
    }

    private function setFilter($filter) {
        $res = $this->client->request('POST', 'src.1.TContentMembersList_OUTPUT.jsp', [
            'form_params' => [
                'FILTER_AKTIVEREN_100' => $this->filters[$filter],
                'BAREBONE_ME' => $this->BAREBONE_ME,
            ]
        ]);

        return $res;
    }

    private function login($user, $pass) {
        // Login to Groepsadmin
        $res = $this->client->request('POST', 'login.do', [
            'form_params' => [
                'lid_lidnummer' => $user,
                'lid_paswoord' => $pass
            ]
        ]);
        
        // Extract user attributes 
        $body = $res->getBody()->getContents();
        $this->extractBAREBONE_ME($body);
        $this->extractFilters($body);

        // Double check user attributes
        if($this->BAREBONE_ME === NULL) throw new Exception('Failed to identify user as Leiding.');

        return $res;
    }

    private function logout() {
        $res = $this->client->request('GET', 'logout.do');
        return $res;
    }

    private function extractFilters($body) {
        $pattern = '|value="(FILTER_AKTIVEREN_100_[^"]+)"[^>]*>\s*([^<\s]+)\s*</option>|';
        preg_match_all($pattern, $body, $matches);
        // dump($matches);
        
        $this->filters = [];
        for ($i = 0; $i < count($matches[1]); $i++) {
            $filterVal = $matches[1][$i];
            $filterKey = $matches[2][$i];
            $this->filters[$filterKey] = $filterVal;
        }
        // dump($this->filters);
    }

    private function extractBAREBONE_ME($body) {
        // Extract the BAREBONE_ME attribute (necessary to change filter, unique per user)
        $pattern = '/name="BAREBONE_ME" value="([^"]+)"/';
        preg_match($pattern, $body, $matches);
        // dump($matches);

        $this->BAREBONE_ME = $matches[1];
        // dump($this->BAREBONEME);
    }
}

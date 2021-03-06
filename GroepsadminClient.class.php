<?php

require 'vendor/autoload.php';
require_once 'util.php';

/**
 * Class GroepsadminClient
 * @author Fantasierijke Saki
 */
class GroepsadminClient
{
    /**
     * Multiple constructor construct
     * See http://php.net/manual/en/language.oop5.decon.php#99903
     */
    function __construct() {
        $a = func_get_args();
        $i = func_num_args();
        if (method_exists($this,$f='__construct'.$i)) {
            call_user_func_array(array($this,$f),$a);
        }
        else {
            throw new Exception(__CLASS__.': Unsupported constructor method');
        }
    } 

    /**
     * Constructor
     * Initiates the Guzzle client and logs in
     * @param logger $logger A logger PSR-3 compliant logging class
     */
    public function __construct1($logger) {
        $this->init($logger);
    }

    /**
     * Constructor
     * Initiates the Guzzle client and logs in
     * @param string $user
     * @param string $pass
     * @param logger $logger A logger PSR-3 compliant logging class
     */
    public function __construct3($user, $pass, $logger) {
        $this->init($logger);
        $this->login($user, $pass);
    }

    /**
     * Util class used in constructors
     * @param logger $logger A logger PSR-3 compliant logging class
     * @return void
     */
    private function init($logger) {
        // Depedency injection
        $this->logger = $logger;

        // Init Guzzle client
        $this->client = new \GuzzleHttp\Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://groepsadmin.scoutsengidsenvlaanderen.be/groepsadmin/',
            // Use a shared client cookie jar for all requests
            'cookies' => true
        ]);
    }

    /**
     * Destruct
     * logs out if necessary
     */
    public function __destruct() {
        if(isset($this->user)) $this->logout();
    }

    /**
     * Facade method which allows to download multiple filters at once.
     * No particular optimizations though...
     * @param array $filters array containing the keys of the filters that aught to be downlaoded
     * @return array mapping the filter keys to the CVS for that filter
     */
    public function downloadFilters($filters) {
        $result = [];
        foreach ($filters as $filter) {
            $result[$filter] = $this->downloadFilter($filter);
        }
        return $result;
    }

    /**
     * Facade method which allows to download a filter
     * @param string $filter the key of the filter that aught to be downlaoded
     * @return string CVS for that filter
     */
    public function downloadFilter($filter) {
        $this->setFilter($filter);
        $res = $this->download();
        return $res;
    }

    /**
     * Downlaod the CSV for a filter.
     * Assumes the filter has already been set.
     * @return string CVS for a filter, FALSE if the request failed
     */
    public function download() {
        $this->logger->debug("{$this->user}\tDownloading CSV");

        // Perform request to download CSV data
        $res = $this->client->request('GET', 'ledenlijst.csv');

        if($res->getStatusCode() == 200) {
            return $res->getBody()->getContents();
        }
        $this->logger->warning("{$this->user}\tFailed to download CSV");
        return FALSE;
    }

    /**
     * Performs a request to set a particular filter
     * @param string $filter filter to be set
     * @return bool TRUE if request was perforemd successful
     */
    public function setFilter($filter) {
        $this->logger->debug("{$this->user}\tSetting filter to $filter");

        // Argument check
        if(!isset($this->filters) || !isset($this->filters[$filter])) {
            $this->logger->error("{$this->user}\tTrying to set undefined filter $filter");
            throw new Exception("{$this->user}\tTrying to set undefined filter $filter");
        }

        // Perform set filter request
        $res = $this->client->request('POST', 'src.1.TContentMembersList_OUTPUT.jsp', [
            'form_params' => [
                'FILTER_AKTIVEREN_100' => $this->filters[$filter],
                'BAREBONE_ME' => $this->BAREBONE_ME,
            ]
        ]);

        if($res->getStatusCode() == 200) {
            return TRUE;
        }
        $this->logger->warning("{$this->user}\tFailed to set filter to $filter");
        return FALSE;
    }

    /**
     * Performs a request to logger and extracts all user specific data
     * @param string $user username
     * @param string $password password
     * @return bool TRUE if request was perforemd successful
     */
    public function login($user, $pass) {
        // logout if logged in
        if(isset($this->user)) {
            $this->logout();
        }

        $this->logger->debug("{$user}\tLogging in");

        // Set SESSION_ID cookie
        $this->client->request('GET');

        // Perform logger request
        $res = $this->client->request('POST', 'login.do', [
            'form_params' => [
                'lid_lidnummer' => $user,
                'lid_paswoord' => $pass
            ]
        ]);
        $body = $res->getBody()->getContents();

        // Set user attributes 
        if($res->getStatusCode() == 200
            && $this->extractBAREBONE_ME($body)
            && $this->extractFilters($body)
        ) {
            $this->user = $user;
            return TRUE;
        }

        $this->logger->warning("$user\tFailed to login");
        return FALSE;
    }

    /**
     * Performs a request to logout.
     * Resets all user data if the request was successful.
     * @return bool TRUE if request was perforemd successful
     */
    public function logout() {
        $this->logger->debug("{$this->user}\tLogging out");

        // Perform logout request
        $res = $this->client->request('GET', 'logout.do');

        if($res->getStatusCode() === 200) {
            // Reset user attributes
            $this->filters = NULL;
            $this->BAREBONE_ME = NULL;

            return TRUE;
        }
        $this->logger->warning("{$this->user}\tFailed to logout");
        return FALSE;
    }

    /**
     * Returns if successfully loggingginggingged in as Leiding
     * @return bool
     */
    public function isLoggedIn() {
        return isset($this->user) && isset($this->BAREBONE_ME);
    }

    /**
     * Util method to extract all filters defined for
     * @param string $body HTTP response body
     * @return bool TRUE if it was able to extract anything
     */
    private function extractFilters($body) {
        if(preg_match_all(self::FILTER_PATTERN, $body, $matches)) {
            $this->filters = [];

            for ($i = 0; $i < count($matches[1]); $i++) {
                $filterVal = trim($matches[1][$i]);
                $filterKey = trim($matches[2][$i]);
                $this->filters[$filterKey] = $filterVal;
            }

            return TRUE;
        }
        $this->logger->warning("Failed to extract filters");
        return FALSE;
    }

    /**
     * Util method to extract the BAREBONE_ME attribute from a response body.
     * Returns boolean wether the request was successful. 
     * @param string $body HTTP response body
     * @return bool TRUE if it was able to extract anything
     */
    private function extractBAREBONE_ME($body) {
        if(preg_match(self::BAREBONE_ME_PATTERN, $body, $matches)) {
            $this->BAREBONE_ME = trim($matches[1]);
            return TRUE;
        }
        $this->logger->warning("Failed to extract BAREBONE_ME");
        return FALSE;
    }

    // Gizzle client used to perform all HTTP requests
    private $client;

    // User specific information
    private $user;
    private $BAREBONE_ME;
    private $filters;

    // logger via dependency injection
    private $logger;

    // Extraction patterns
    const BAREBONE_ME_PATTERN = '|name="BAREBONE_ME"\s+value="([^"]+)"|';
    const FILTER_PATTERN = '|value="(FILTER_AKTIVEREN_100_[^"]+)"[^>]*>\s*([^<]+?)\s*(\(A2420G\)\s*)?</option>|';
}

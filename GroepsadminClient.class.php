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
     * Initiates the Guzzle client and logs in the user
     * @param logger $logger A logger PSR-3 compliant logging class
     */
    public function __construct1($logger) {
        $this->init($logger);
    }

    /**
     * Constructor
     * Initiates the Guzzle client and logs in the user
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
     * Logs out the user if necessary
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
        return $res->getBody();
    }

    /**
     * Downlaod the CSV for a filter.
     * Assumes the filter has already been set.
     * @return string CVS for a filter, FALSE if the request failed
     */
    public function download() {
        $this->logger->debug("Downloading CSV for user {$this->user}");

        // Perform request to download CSV data
        $res = $this->client->request('GET', 'ledenlijst.csv');

        if($res->getStatusCode() == 200) {
            return $res->getBody()->getContents();
        }
        $this->logger->warning("Failed to download CSV for filter for user {$this->user}");
        return FALSE;
    }

    /**
     * Performs a request to set a particular filter
     * @param string $filter filter to be set
     * @return bool TRUE if request was perforemd successful
     */
    public function setFilter($filter) {
        $this->logger->debug("Setting filter $filter for user {$this->user}");

        // Argument check
        if(!isset($this->filters) || !isset($this->filters[$filter])) {
            $this->logger->error("Trying to set undefined filter $filter for user {$this->user}");
            throw new Exception('Trying to set undefined filter');
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
        $this->logger->warning("Failed to set filter $filter for user {$this->user}");
        return FALSE;
    }

    /**
     * Performs a request to login the user and extracts all user specific data
     * @param string $user username
     * @param string $password password
     * @return bool TRUE if request was perforemd successful
     */
    public function login($user, $pass) {
        // Logout if logged in
        if(isset($this->user)) {
            $this->logout();
        }

        $this->logger->debug("Logging in as $user");

        // Set SESSION_ID cookie
        $this->client->request('GET');

        // Perform login request
        $res = $this->client->request('POST', 'login.do', [
            'form_params' => [
                'lid_lidnummer' => $user,
                'lid_paswoord' => $pass
            ]
        ]);

        if($res->getStatusCode() == 200) {
            $this->user = $user;

            // Set user attributes 
            $body = $res->getBody()->getContents();
            $this->extractBAREBONE_ME($body);
            $this->extractFilters($body);

            return TRUE;
        }
        $this->logger->warning("Failed to login as $user");
        return FALSE;
    }

    /**
     * Performs a request to logout the user.
     * Resets all user data if the request was successful.
     * @return bool TRUE if request was perforemd successful
     */
    public function logout() {
        $this->logger->debug("Logging out as {$this->user}");

        // Perform logout request
        $res = $this->client->request('GET', 'logout.do');

        if($res->getStatusCode() === 200) {
            // Reset user attributes
            $this->filters = NULL;
            $this->BAREBONE_ME = NULL;

            return TRUE;
        }
        $this->logger->warning("Failed to logout as {$this->user}");
        return FALSE;
    }

    /**
     * Specifies if user is logged in as Leiding
     * @return bool
     */
    public function isLoggedInAsLeiding() {
        return isset($this->user) && isset($this->BAREBONE_ME);
    }

    /**
     * Util method to extract all filters defined for the user
     * @param string $body HTTP response body
     * @return bool TRUE if it was able to extract anything
     */
    private function extractFilters($body) {
        if(preg_match_all(self::FILTER_PATTERN, $body, $matches)) {
            $this->filters = [];

            for ($i = 0; $i < count($matches[1]); $i++) {
                $filterVal = $matches[1][$i];
                $filterKey = $matches[2][$i];
                $this->filters[$filterKey] = $filterVal;
            }

            return TRUE;
        }
        $this->logger->warning("Failed to extract filters for user {$this->user}");
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
            $this->BAREBONE_ME = $matches[1];
            return TRUE;
        }
        $this->logger->warning("Failed to extract BAREBONE_ME for user {$this->user}");
        return FALSE;
    }

    // Gizzle client used to perform all HTTP requests
    private $client;

    // User specific information
    private $user;
    private $BAREBONE_ME;
    private $filters;

    // Logger via dependency injection
    private $logger;

    // Extraction patterns
    const BAREBONE_ME_PATTERN = '|name="BAREBONE_ME" value="([^"]+)"|';
    const FILTER_PATTERN = '|value="(FILTER_AKTIVEREN_100_[^"]+)"[^>]*>\s*([^<\s]+)\s*</option>|';
}

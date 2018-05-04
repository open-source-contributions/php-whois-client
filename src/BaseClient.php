<?php
namespace MallardDuck\Whois;

use TrueBV\Punycode;
use League\Uri\Components\Host;
use Hoa\Socket\Client as SocketClient;
use MallardDuck\Whois\WhoisServerList\AbstractLocator;
use MallardDuck\Whois\WhoisServerList\DomainLocator;
use MallardDuck\Whois\Exceptions\MissingArgException;

/**
 * The Whois Client Class.
 *
 * @author mallardduck <dpock32509@gmail.com>
 *
 * @copyright lucidinternets.com 2018
 *
 * @version 1.0.0
 */
class BaseClient
{

    /**
     * The TLD Whois locator class.
     * @var AbstractLocator
     */
    protected $whoisLocator;

    /**
     * The Unicode for IDNA.
     * @var \TrueBV\Punycode
     */
    protected $punycode;

    /**
     * The carriage return line feed character comobo.
     * @var string
     */
    protected $clrf = "\r\n";

    /**
     * The input domain provided by the user.
     * @var string
     */
    public $inputDomain;

    /**
     * The parsed domain after validating and encoding.
     * @var string
     */
    public $parsedDomain;

    /**
     * Construct the Whois Client Class.
     */
    public function __construct()
    {
        $this->punycode = new Punycode();
        $this->whoisLocator = new DomainLocator();
    }

    /**
     * A unicode safe method for making whois requests.
     *
     * The main difference with this method is the benefit of punycode domains.
     *
     * @param  string $domain      The domain or IP being looked up.
     * @param  string $whoisServer The whois server being queried.
     *
     * @return string              The raw results of the query response.
     */
    public function makeWhoisRequest($domain, $whoisServer)
    {
        $this->parseWhoisDomain($domain);
        // Form a socket connection to the whois server.
        return $this->makeWhoisRawRequest($this->parsedDomain, $whoisServer);
    }

    /**
     * A function for making a raw Whois request.
     * @param  string $domain      The domain or IP being looked up.
     * @param  string $whoisServer The whois server being queried.
     *
     * @return string              The raw results of the query response.
     */
    public function makeWhoisRawRequest($domain, $whoisServer)
    {
        // Form a tcp socket connection to the whois server.
        $client = new SocketClient('tcp://'.$whoisServer.':43', 10);
        $client->connect();
        // Send the domain name requested for whois lookup.
        $client->writeString($domain.$this->clrf);
        // Read the full output of the whois lookup.
        $response = $client->readAll();
        // Disconnect the connections to prevent network/performance issues.
        $client->disconnect();

        return $response;
    }

    /**
     * Uses the League Uri Hosts component to get the search able hostname in PHP 5.6 and 7.
     *
     * @param string $domain The domain or IP being looked up.
     *
     * @return string
     */
    protected function getSearchableHostname($domain)
    {
        // Attempt to parse the domains Host component and get the registrable parts.
        $host = new Host($domain);
        return $host->getRegistrableDomain();
    }

    /**
     * Takes the user provided domain and parses then encodes just the registerable domain.
     * @param  string $domain The user provided domain.
     *
     * @return string Returns the parsed domain.
     */
    protected function parseWhoisDomain($domain)
    {
        if (empty($domain)) {
            throw new MissingArgException("Must provide a domain name when using lookup method.");
        }
        $this->inputDomain = $domain;

        // Check domain encoding
        $encoding = mb_detect_encoding($domain);

        $processedDomain = $this->getSearchableHostname($domain);

        // Punycode the domain if it's Unicode
        if ("UTF-8" === $encoding) {
            $processedDomain = $this->punycode->encode($processedDomain);
        }
        $this->parsedDomain = $processedDomain;

        return $processedDomain;
    }
}
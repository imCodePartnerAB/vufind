<?php

/**
 * Builds the configured BankID provider.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

/**
 * Builds the configured BankID provider.
 */
class ProviderBuilder
{
    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface|null
     */
    protected $httpService;

    /**
     * Constructor
     *
     * @param \VuFindHttp\HttpServiceInterface|null $httpService HTTP service
     */
    public function __construct($httpService = null)
    {
        $this->httpService = $httpService;
    }

    /**
     * Create the provider.
     *
     * @param BankIdConfig $config      Settings
     * @param string       $simulateUrl URL of the simulation page
     *
     * @return ProviderInterface
     */
    public function create(BankIdConfig $config, string $simulateUrl): ProviderInterface
    {
        return $config->isSimulated()
            ? new SimulatedProvider($config->getSimulatedPersonnummer(), $simulateUrl)
            : new OidcProvider($config, $this->httpService);
    }
}

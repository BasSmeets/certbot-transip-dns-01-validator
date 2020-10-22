<?php

namespace RoyBongers\CertbotDns01\Providers;

use Psr\Log\LoggerInterface;
use RoyBongers\CertbotDns01\Certbot\ChallengeRecord;
use RoyBongers\CertbotDns01\Config;
use Transip\Api\Library\Entity\Domain\DnsEntry;
use Transip\Api\Library\TransipAPI;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;

class TransIp implements ProviderInterface
{
    private LoggerInterface $logger;

    private Config $config;

    private TransipAPI $client;

    private array $domainNames = [];

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Create a TXT DNS record via the provider's API.
     *
     * @param  ChallengeRecord  $challengeRecord
     */
    public function createChallengeDnsRecord(ChallengeRecord $challengeRecord): void
    {
        $challengeDnsEntry = new DnsEntry();
        $challengeDnsEntry->setName($challengeRecord->getRecordName());
        $challengeDnsEntry->setExpire(60);
        $challengeDnsEntry->setType(DnsEntry::TYPE_TXT);
        $challengeDnsEntry->setContent($challengeRecord->getValidation());

        $this->getTransipApiClient()
            ->domainDns()
            ->addDnsEntryToDomain($challengeRecord->getDomain(), $challengeDnsEntry);
    }

    /**
     * Remove the created TXT record via the provider's API.
     *
     * @param  ChallengeRecord  $challengeRecord
     */
    public function cleanChallengeDnsRecord(ChallengeRecord $challengeRecord): void
    {
        $client = $this->getTransipApiClient();
        $dnsEntries = $client->domainDns()->getByDomainName($challengeRecord->getDomain());

        foreach ($dnsEntries as $dnsEntry) {
            if ($dnsEntry->getName() === $challengeRecord->getRecordName() &&
                $dnsEntry->getContent() === $challengeRecord->getValidation()
            ) {
                $this->logger->debug(sprintf(
                    'Removing challenge DNS record(%s 60 TXT %s)',
                    $dnsEntry->getName(),
                    $dnsEntry->getContent()
                ));
                $client->domainDns()->removeDnsEntry($challengeRecord->getDomain(), $dnsEntry);
            }
        }
    }

    /**
     * Return a simple array containing the domain names that can be managed via the API.
     *
     * @return iterable
     */
    public function getDomainNames(): iterable
    {
        if (empty($this->domainNames)) {
            $domains = $this->getTransipApiClient()->domains()->getAll();
            foreach ($domains as $domain) {
                $this->domainNames[] = $domain->getName();
            }
        }

        $this->logger->debug(sprintf('Domain names available: %s', implode(', ', $this->domainNames)));

        return $this->domainNames;
    }

    public function getTransipApiClient(): TransipAPI
    {
        if ($this->client instanceof TransipAPI) {
            return $this->client;
        }

        $login = $this->config->get('transip_login', $this->config->get('login'));
        $privateKey = $this->config->get('transip_private_key', $this->config->get('private_key'));
        $generateWhitelistOnlyTokens = (bool) $this->config->get('transip_whitelist_only_token', true);

        return new TransipAPI($login, $privateKey, $generateWhitelistOnlyTokens);
    }
}

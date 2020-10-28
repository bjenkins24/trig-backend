<?php

namespace Tests\Feature\Modules\Card\Integrations\Google\Fakes;

class FakeDirectoryDomain
{
    public string $etag = 'UvbVMfSqV-62c_9WujxISgvFB8RiUAabE6iZDX9mAp8/NMj57vGwkfU35M_LaaLLUHuSXyI';
    public string $kind = 'admin#directory#domains';
    public array $domains = [];

    public function __construct()
    {
        $this->domains = [
            new DomainFake(),
        ];
    }
}

class DomainFake
{
    public string $creationTime = '1541947241460';
    public string $domainName = 'trytrig.com';
    public string $etag = 'UvbVMfSqV-62c_9WujxISgvFB8RiUAabE6iZDX9mAp8/iGcvkqhk6sFrmwv_CZQJRWznM00';
    public bool $isPrimary = true;
    public string $kind = 'admin#directory#domain';
    public bool $verified = true;
    public array $domainAliases;

    public function __construct()
    {
        $this->domainAliases = [
            new FakeDomainAlias(),
        ];
    }
}

class FakeDomainAlias
{
    public string $creationTime = '1541947241460';
    public string $domainAliasName = 'trytrig.com.test-google-a.com';
    public string $etag = 'UvbVMfSqV-62c_9WujxISgvFB8RiUAabE6iZDX9mAp8/pBDHLNFAWBBu_GfBTZwXvD55MaY';
    public string $kind = 'admin#directory#domainAlias';
    public string $parentDomainName = 'trytrig.com';
    public bool $verified = true;
}

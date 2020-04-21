<?php

namespace Tests\Feature\Modules\Card\Integrations\Fakes;

class FakeDirectoryDomain
{
    public $etag = 'UvbVMfSqV-62c_9WujxISgvFB8RiUAabE6iZDX9mAp8/NMj57vGwkfU35M_LaaLLUHuSXyI';
    public $kind = 'admin#directory#domains';
    public $domains = [];

    public function __construct()
    {
        $this->domains = [
            new FakeDomain(),
        ];
    }
}

class DomainFake
{
    public $creationTime = '1541947241460';
    public $domainName = 'trytrig.com';
    public $etag = 'UvbVMfSqV-62c_9WujxISgvFB8RiUAabE6iZDX9mAp8/iGcvkqhk6sFrmwv_CZQJRWznM00';
    public $isPrimary = true;
    public $kind = 'admin#directory#domain';
    public $verified = true;
    public $domainAliases;

    public function __construct()
    {
        $this->domainAliases = [
            new FakeDomainAlias(),
        ];
    }
}

class FakeDomainAlias
{
    public $creationTime = '1541947241460';
    public $domainAliasName = 'trytrig.com.test-google-a.com';
    public $etag = 'UvbVMfSqV-62c_9WujxISgvFB8RiUAabE6iZDX9mAp8/pBDHLNFAWBBu_GfBTZwXvD55MaY';
    public $kind = 'admin#directory#domainAlias';
    public $parentDomainName = 'trytrig.com';
    public $verified = true;
}

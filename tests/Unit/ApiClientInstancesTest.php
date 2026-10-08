<?php

declare(strict_types=1);

namespace Ufee\AmoV4\Tests\Unit;

use Ufee\AmoV4\ApiClient;
use Ufee\AmoV4\Tests\TestCase;

final class ApiClientInstancesTest extends TestCase
{
	private array $keys = [];

	protected function tearDown(): void
	{
		foreach ($this->keys as $key) {
			if (ApiClient::hasInstance($key)) {
				ApiClient::removeInstance($key);
			}
		}
		parent::tearDown();
	}

	private function make(string $client_id, string $domain): ApiClient
	{
		$client = ApiClient::setInstance([
			'domain' => $domain,
			'client_id' => $client_id,
			'client_secret' => 'secret',
			'redirect_uri' => 'https://localhost/oauth',
			'zone' => 'ru',
		]);
		$this->keys[] = $client->getKey();
		return $client;
	}

	public function testTwoDomainsWithSameClientIdKeepSeparateInstances(): void
	{
		$a = $this->make('shared', 'alpha');
		$b = $this->make('shared', 'beta');

		self::assertSame('shared:alpha', $a->getKey());
		self::assertSame('shared:beta', $b->getKey());
		self::assertSame($a, ApiClient::getInstance(ApiClient::instanceKey('shared', 'alpha')));
		self::assertSame($b, ApiClient::getInstance(ApiClient::instanceKey('shared', 'beta')));
		self::assertSame('alpha.amocrm.ru', $a->leads()->instance->getParam('crm_host'));
		self::assertSame('beta.amocrm.ru', $b->leads()->instance->getParam('crm_host'));
	}

	public function testBareClientIdResolvesOnlyWhenUnique(): void
	{
		$a = $this->make('solo', 'alpha');
		self::assertSame($a, ApiClient::getInstance('solo'));

		$this->make('solo', 'beta');
		$this->expectException(\Exception::class);
		ApiClient::getInstance('solo');
	}

	public function testOldestInstanceIsEvictedAboveLimit(): void
	{
		$first = $this->make('lru', 'd0');
		for ($i = 1; $i <= ApiClient::MAX_INSTANCES; $i++) {
			$this->make('lru', 'd' . $i);
		}
		self::assertFalse(ApiClient::hasInstance($first->getKey()));
		self::assertTrue(ApiClient::hasInstance(ApiClient::instanceKey('lru', 'd' . ApiClient::MAX_INSTANCES)));
	}
}

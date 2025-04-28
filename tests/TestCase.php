<?php declare(strict_types=1);

namespace Antwerpes\Soap\Tests;

use Antwerpes\Soap\Facades\Soap;
use Antwerpes\Soap\SoapServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected $loadEnvironmentVariables = true;

    /**
     * Load package service provider.
     *
     * @param Application $app
     *
     * @return string[]
     */
    protected function getPackageProviders($app)
    {
        return [SoapServiceProvider::class];
    }

    /**
     * Load package alias.
     *
     * @param Application $app
     *
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'Soap' => Soap::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default wsse
        $app['config']->set('soap.clients.laravel_soap', [
            'base_wsdl' => __DIR__.'/Fixtures/Wsdl/weather.wsdl',
            'with_wsse' => [
                'user_token_name' => 'username',
                'user_token_password' => 'password',
            ],
        ]);
        $app['config']->set('soap.code.path', __DIR__.'/app');
        $app['config']->set('soap.code.namespace', 'App\Soap');
    }
}

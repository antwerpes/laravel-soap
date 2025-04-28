<?php declare(strict_types=1);

namespace Antwerpes\Soap;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SoapServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('soap')
            ->hasConfigFile('soap');
    }

    public function packageRegistered(): void
    {
        $this->app->bind('Soap', fn () => new SoapFactory);
    }
}

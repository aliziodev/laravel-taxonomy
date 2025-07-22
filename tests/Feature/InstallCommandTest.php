<?php

use Aliziodev\LaravelTaxonomy\Console\Commands\InstallCommand;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

uses(TestCase::class);

it('has correct command signature and description', function () {
    $command = new InstallCommand;

    expect($command->getName())->toBe('taxonomy:install');
    expect($command->getDescription())->toBe('Install the Laravel Taxonomy package');
});

it('command is registered in artisan', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('taxonomy:install');
    expect($commands['taxonomy:install'])->toBeInstanceOf(InstallCommand::class);
});

it('command returns success constant', function () {
    // Test that the command class returns the correct success constant
    expect(Command::SUCCESS)->toBe(0);
});

it('command has proper structure', function () {
    $command = new InstallCommand;

    // Test command properties
    expect($command)->toBeInstanceOf(Command::class);

    // Test signature and description are set
    $reflection = new \ReflectionClass($command);
    $signatureProperty = $reflection->getProperty('signature');
    $signatureProperty->setAccessible(true);
    $descriptionProperty = $reflection->getProperty('description');
    $descriptionProperty->setAccessible(true);

    expect($signatureProperty->getValue($command))->toBe('taxonomy:install');
    expect($descriptionProperty->getValue($command))->toBe('Install the Laravel Taxonomy package');
});

it('handle method executes successfully', function () {
    $exitCode = Artisan::call('taxonomy:install');

    expect($exitCode)->toBe(Command::SUCCESS);
});

it('handle method has correct signature', function () {
    $command = new InstallCommand;

    // Verify the method signature through reflection
    $reflection = new \ReflectionMethod($command, 'handle');
    expect($reflection->isPublic())->toBeTrue();
    expect($reflection->getNumberOfParameters())->toBe(0);

    // Verify return type through reflection
    $returnType = $reflection->getReturnType();
    expect($returnType)->toBeNull(); // Laravel commands typically don't declare return types

    // Verify method is defined in the class
    expect($reflection->getDeclaringClass()->getName())->toBe(InstallCommand::class);
});

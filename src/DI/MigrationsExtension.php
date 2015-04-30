<?php

/**
 * This file is part of Zenify
 * Copyright (c) 2014 Tomas Votruba (http://tomasvotruba.cz)
 */

namespace Zenify\DoctrineMigrations\DI;

use Doctrine\DBAL\Migrations\Tools\Console\Command\AbstractCommand;
use Nette\DI\CompilerExtension;
use Symfony\Component\Console\Application;
use Symnedi\EventDispatcher\DI\EventDispatcherExtension;
use Zenify\DoctrineMigrations\CodeStyle\CodeStyle;
use Zenify\DoctrineMigrations\Configuration\Configuration;
use Zenify\DoctrineMigrations\Exception\DI\MissingExtensionException;


final class MigrationsExtension extends CompilerExtension
{

	/**
	 * @var string[]
	 */
	private $defaults = [
		'table' => 'doctrine_migrations',
		'directory' => '%appDir%/../migrations',
		'namespace' => 'Migrations',
		'codingStandard' => CodeStyle::INDENTATION_TABS
	];


	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration()
	{
		$this->ensureSymnediEventDispatcherExtensionIsRegistered();

		$containerBuilder = $this->getContainerBuilder();

		$this->compiler->parseServices(
			$containerBuilder,
			$this->loadFromFile(__DIR__ . '/../config/services.neon')
		);

		$config = $this->getValidatedConfig();

		$containerBuilder->addDefinition($this->prefix('codeStyle'))
			->setClass('Zenify\DoctrineMigrations\CodeStyle\CodeStyle')
			->setArguments([$config['codingStandard']]);

		$this->addConfigurationDefinition($config);
	}


	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile()
	{
		$containerBuilder = $this->getContainerBuilder();
		$containerBuilder->prepareClassList();

		$this->setConfigurationToCommands();
		$this->loadCommandsToApplication();
	}


	private function addConfigurationDefinition(array $config)
	{
		$containerBuilder = $this->getContainerBuilder();
		$containerBuilder->addDefinition($this->prefix('configuration'))
			->setClass('Zenify\DoctrineMigrations\Configuration\Configuration')
			->addSetup('setMigrationsTableName', [$config['table']])
			->addSetup('setMigrationsDirectory', [$config['directory']])
			->addSetup('setMigrationsNamespace', [$config['namespace']]);
	}


	private function setConfigurationToCommands()
	{
		$containerBuilder = $this->getContainerBuilder();
		$configurationDefinition = $containerBuilder->getDefinition($containerBuilder->getByType('Zenify\DoctrineMigrations\Configuration\Configuration'));

		foreach ($containerBuilder->findByType('Doctrine\DBAL\Migrations\Tools\Console\Command\AbstractCommand') as $commandDefinition) {
			$commandDefinition->addSetup('setMigrationConfiguration', ['@' . $configurationDefinition->getClass()]);
		}
	}


	private function loadCommandsToApplication()
	{
		$containerBuilder = $this->getContainerBuilder();
		$applicationDefinition = $containerBuilder->getDefinition($containerBuilder->getByType('Symfony\Component\Console\Application'));
		foreach ($containerBuilder->findByType('Doctrine\DBAL\Migrations\Tools\Console\Command\AbstractCommand') as $name => $commandDefinition) {
			$applicationDefinition->addSetup('add', ['@' . $name]);
		}
	}


	/**
	 * @return array
	 */
	private function getValidatedConfig()
	{
		$configuration = $this->getConfig($this->defaults);
		$this->validateConfig($configuration);

		$configuration = $this->keepBcForDirsOption($configuration);
		$configuration['directory'] = $this->getContainerBuilder()->expand($configuration['directory']);

		return $configuration;
	}


	/**
	 * @deprecated Old `dirs` option to be removed in 3.0, use `directory` instead.
	 *
	 * @return array
	 */
	private function keepBcForDirsOption(array $configuration)
	{
		if (isset($configuration['dirs']) && count($configuration['dirs'])) {
			$configuration['directory'] = reset($configuration['dirs']);
		}
		return $configuration;
	}


	private function ensureSymnediEventDispatcherExtensionIsRegistered()
	{
		if ( ! $this->compiler->getExtensions('Symnedi\EventDispatcher\DI\EventDispatcherExtension')) {
			throw new MissingExtensionException(
				sprintf('Please register required extension "%s" to your config.', 'Symnedi\EventDispatcher\DI\EventDispatcherExtension')
			);
		}
	}

}

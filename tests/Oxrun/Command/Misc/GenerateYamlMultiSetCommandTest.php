<?php
/**
 * Created by PhpStorm.
 * User: tobi
 * Date: 2019-03-02
 * Time: 17:50
 */

namespace Oxrun\Command;

use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;
use Oxrun\Application;
use Oxrun\Command\Misc\GenerateYamlMultiSetCommand;
use Oxrun\CommandCollection\EnableAdapter;
use Oxrun\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GenerateYamlMultiSetCommandTest
 * @package Oxrun\Command
 */
class GenerateYamlMultiSetCommandTest extends TestCase
{
    protected static $unlinkFile = null;

    public function testExecute()
    {
        $app = new Application();
        $app->add(new EnableAdapter(new GenerateYamlMultiSetCommand()));

        $command = $app->find('misc:generate:yaml:multiset');

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array(
                'command' => $command->getName(),
                '--configfile' => 'shopConfigs.yml',
            )
        );
        $expectPath = self::$unlinkFile = $app->getOxrunConfigPath() . 'shopConfigs.yml';

        $this->assertContains('Config saved. use `oxrun config:multiset shopConfigs.yml`', $commandTester->getDisplay());
        $this->assertFileExists($expectPath);
    }

    public function testExportListOfVariabels()
    {
        $app = new Application();
        $app->add(new EnableAdapter(new GenerateYamlMultiSetCommand()));

        Registry::getConfig()->saveShopConfVar('str', 'unitVarB', 'abcd1');
        Registry::getConfig()->saveShopConfVar('str', 'unitVarC', 'cdef1');

        $dev_yml = ['config' => ['1' => ['varA' => 'besteht']]];
        $shopDir = ['oxrun_config' => ['dev.yml' => Yaml::dump($dev_yml)]];
        $app->checkBootstrapOxidInclude($this->fillShopDir($shopDir)->getVirtualBootstrap());


        $command = $app->find('misc:generate:yaml:multiset');

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array(
                'command' => $command->getName(),
                '--configfile' => 'dev.yml',
                '--oxvarname' => 'unitVarB,unitVarC',
            )
        );

        $actual = Yaml::parse(file_get_contents($app->getOxrunConfigPath() . 'dev.yml'));
        $expect = ['config' => [
            '1' => [
                'varA' => 'besteht',
                'unitVarB' => 'abcd1',
                'unitVarC' => 'cdef1',
            ]
        ]];
        $this->assertEquals($expect, $actual);
    }

    public function testExportModullVariable()
    {
        $app = new Application();
        $app->add(new EnableAdapter(new GenerateYamlMultiSetCommand()));

        Registry::getConfig()->saveShopConfVar('str', 'unitModuleB', 'abcd1', 1, 'module:unitTest');
        Registry::getConfig()->saveShopConfVar('str', 'unitModuleW', 'cdef1', 1, 'module:unitNext');

        $app->checkBootstrapOxidInclude($this->fillShopDir([])->getVirtualBootstrap());

        $command = $app->find('misc:generate:yaml:multiset');

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array(
                'command' => $command->getName(),
                '--oxmodule' => 'module:unitTest, module:unitNext',
            )
        );

        $actual = Yaml::parse(file_get_contents($app->getOxrunConfigPath() . 'dev.yml'));
        $expect = ['config' => [
            '1' => [
                'unitModuleB' => [
                    'variableType' => 'str',
                    'variableValue' => 'abcd1',
                    'moduleId' => 'module:unitTest'
                ],
                'unitModuleW' => [
                    'variableType' => 'str',
                    'variableValue' => 'cdef1',
                    'moduleId' => 'module:unitNext'
                ],
            ]
        ]];

        $this->assertEquals($expect, $actual);
    }

    public function testExportModulVariableNameAndShop2()
    {
        $app = new Application();
        $app->add(new EnableAdapter(new GenerateYamlMultiSetCommand()));

        Registry::getConfig()->saveShopConfVar('str', 'unitSecondShopName', 'Mars', 2, 'module:unitMars');
        Registry::getConfig()->saveShopConfVar('str', 'unitEgal',           'none', 2, 'module:unitMars');

        $app->checkBootstrapOxidInclude($this->fillShopDir([])->getVirtualBootstrap());

        $command = $app->find('misc:generate:yaml:multiset');

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array(
                'command' => $command->getName(),
                '--oxvarname' => 'unitSecondShopName',
                '--oxmodule' => 'module:unitMars',
                '--shopId' => '2',
            )
        );

        $actual = Yaml::parse(file_get_contents($app->getOxrunConfigPath() . 'dev.yml'));
        $expect = ['config' => [
            '2' => [
                'unitSecondShopName' => [
                    'variableType' => 'str',
                    'variableValue' => 'Mars',
                    'moduleId' => 'module:unitMars'
                ]
            ]
        ]];

        $this->assertEquals($expect, $actual);
    }

    protected function tearDown()
    {
        if (self::$unlinkFile) {
            @unlink(self::$unlinkFile);
        }

        DatabaseProvider::getDb()->execute('DELETE FROM `oxconfig` WHERE `OXVARNAME` LIKE "unit%"');
        parent::tearDown();
    }
}

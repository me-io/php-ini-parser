<?php

namespace Tests\Ini;

use Ini\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    /**
     * This is a test-case I wrote because I think there are small bugs
     * in {@link IniParser}. Just to see if a very basic .ini would be
     * parsed as expected.
     *
     * @return void
     */
    public function testIniParserVsParseIniString()
    {
        $parseIniString = parse_ini_file(__DIR__ . '/../fixtures/fixture00.ini', true);

        $configObj = $this->getConfig('fixture00.ini');
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $this->assertSame($config, $parseIniString);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConfigNotFound()
    {
        new Parser('/this/should/never/exist.ini');
    }

    /**
     * A slightly more complex test to confirm parsing works.
     *
     * @return void
     */
    public function testParser()
    {
        $configObj = $this->getConfig('fixture01.ini');
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $expected = [
            'production' => [
                'hello' => 'world',
                'super' => ['funny' => 'config'],
            ],
        ];

        $this->assertSame($expected, $config);
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage Need ini content to parse.
     */
    public function testParseShouldReturnLogicException()
    {
        $parser = new Parser();
        $parser->parse(null);
    }

    public function testProcessCanParsesAStringWithINIContents()
    {
        $iniString = '[production]
        
        hello = world
        super.funny = config';

        $parser = new Parser();
        $configObj = $parser->process($iniString);
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $expected = [
            'production' => [
                'hello' => 'world',
                'super' => ['funny' => 'config'],
            ],
        ];

        $this->assertSame($expected, $config);
    }

    /**
     * Confirm that the 'dev' environment inherits all values from the 'prod' environment.
     *
     * @return void
     */
    public function testInheritance()
    {
        $configObj = $this->getConfig('fixture02.ini');
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $expected = [
            'prod' => [
                'hello' => 'world',
            ],
            'dev' => [
                'hello' => 'world',
            ],
        ];

        $this->assertSame($expected, $config);
    }

    /**
     * Test ArrayObject implementation so we can access the configuration
     * OO-style.
     *
     * @return void
     */
    public function testArrayObject()
    {
        $configObj = $this->getConfig('fixture02.ini');

        $this->assertObjectHasAttribute('prod', $configObj);
        $this->assertObjectHasAttribute('dev', $configObj);

        $this->assertEquals($configObj->prod, $configObj->dev);
        $this->assertEquals('world', $configObj->dev->hello);
        $this->assertEquals('world', $configObj->prod->hello);
    }

    /**
     * Make sure stacked configuration settings are always 'ArrayObject'.
     *
     * @return void
     */
    public function testArrayObjectComplex()
    {
        $configObj = $this->getConfig('fixture03.ini');

        $this->assertInstanceOf('ArrayObject', $configObj->production->database);
        $this->assertEquals('mysql:host=127.0.0.1', $configObj->production->database->connection);
    }

    /**
     * Test that array literals are parsed correctly
     *
     * @return void
     */
    public function testArrayLiteral()
    {
        $configObj = $this->getConfig('fixture04.ini');

        $this->assertInternalType('array', $configObj['array1']);
        $this->assertEquals(['a', 'b', 'c'], $configObj['array1']);

        $this->assertInternalType('array', $configObj['sect1']['array2']);
        $this->assertEquals(['d', 'e', 'f'], $configObj['sect1']['array2']);
    }

    /**
     * Test that inheriting from a section defined later works
     */
    public function testForwardReferenceInheritance()
    {
        $configObj = $this->getConfig('fixture05.ini');

        $this->assertEquals('xyz', $configObj['s2']['value']);
        $this->assertEquals('abc', $configObj['s1']['value']);
    }

    /**
     * Test that inheriting from an undefined section gives a nice error
     *
     * @expectedException \UnexpectedValueException
     */
    public function testInvalidSectionReference()
    {
        $configObj = $this->getConfig('fixture06.ini');
    }

    /**
     * Test section inheritance from the top level
     *
     * @return void
     */
    public function testSectionInheritGlobal()
    {
        $configObj = $this->getConfig('fixture04.ini');

        $this->assertEquals('bar', $configObj['sect2']['foo']);
        $this->assertEquals($configObj['array1'], $configObj['sect2']['array1']);
    }

    /**
     * Test appending values during inheritance
     */
    public function testAppendValues()
    {
        $configObj = $this->getConfig('fixture04.ini');

        $this->assertEquals(['a', 'b', 'c', 'x', 'y', 'z'], $configObj['sect3']['array1']);
        $this->assertEquals('barbaz', $configObj['sect3']['foo']);
    }

    /**
     * This is the example from the README.
     *
     * @return void
     */
    public function testComplex()
    {
        $configObj = $this->getConfig('fixture03.ini');
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $expected = [
            'environment' => 'testing',
            'testing' => [
                'debug' => true,
                'database' => [
                    'connection' => 'mysql:host=127.0.0.1',
                    'name' => 'test',
                    'username' => '',
                    'password' => '',
                ],
                'secrets' => [1, 2, 3],
            ],
            'staging' => [
                'debug' => true,
                'database' => [
                    'connection' => 'mysql:host=127.0.0.1',
                    'name' => 'stage',
                    'username' => 'staging',
                    'password' => 12345,
                ],
                'secrets' => [1, 2, 3],
            ],
            'production' => [
                'debug' => false,
                'database' => [
                    'connection' => 'mysql:host=127.0.0.1',
                    'name' => 'production',
                    'username' => 'root',
                    'password' => 12345,
                ],
                'secrets' => [1, 2, 3],
            ],
        ];

        $this->assertEquals($expected, $config);
    }

    /**
     * Tests the parametric values i.e. parameter has more parameters against it
     *
     * @return void
     */
    public function testParametricValues()
    {
        $configObj = $this->getConfig('fixture12.ini', [
            'parametric_parsing' => true,
        ]);
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $expected = [
            'guest' => [
                'user_auth_login' => [
                    'allowed' => 1,
                    'app' => [
                        'FLIGHT_API',
                        'HOTEL_API',
                    ],
                    'verb' => '*',
                ],
                'user_account_register' => [
                    'allowed' => 1,
                ],
            ],
        ];

        $this->assertEquals($expected, $config);
    }

    /**
     * Tests that appending to a potentially non-existent array works as expected
     * Spawned by https://github.com/austinhyde/IniParser/issues/6
     * and https://github.com/austinhyde/IniParser/pull/7
     *
     * @return void
     */
    public function testArrayAppend()
    {
        $configObj = $this->getConfig('fixture07.ini');
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);
        $expected = [1, 2, 'c'];

        $this->assertArrayHasKey('a', $config);
        $this->assertEquals($expected, $config['a']);
    }

    /**
     * Tests that section names are allowed to be numeric
     *
     * @return void
     */
    public function testNumericSections()
    {
        $configObj = $this->getConfig('fixture08.ini');
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $this->assertEquals(['a', 0, 1], array_keys($config));
        $this->assertEquals(1, $config['a']);

        $this->assertArrayHasKey('b', $config[0]);
        $this->assertEquals(2, $config[0]['b']);

        $this->assertArrayHasKey('c', $config[1]);
        $this->assertEquals(3, $config[1]['c']);
    }

    /**
     * Tests that ArrayObjects are only used when the flag is set
     *
     * @return void
     */
    public function testUseArrayObject()
    {
        $parser = new Parser(__DIR__ . '/../fixtures/fixture01.ini');
        $configObj = $parser->parse();

        $this->assertInstanceOf('ArrayObject', $configObj);

        $parser->use_array_object = false;
        $configArr = $parser->parse();

        $this->assertInternalType('array', $configArr);
    }

    /**
     * Tests that arrays with 0 as key work as expected
     *
     * @return void
     */
    public function testArrayWithZeroAsKey()
    {
        $configObj = $this->getConfig('fixture09.ini');
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $this->assertObjectHasAttribute('helloworld', $configObj);
        $this->assertObjectHasAttribute('hello', $configObj->helloworld);

        $this->assertEquals((array)$configObj->helloworld->hello, [1 => 'world', 0 => 'hello']);
    }

    /**
     * Tests that json is parsed as expected
     *
     * @return void
     */
    public function testJson()
    {
        $configObj = $this->getConfig('fixture10.ini',
            ['array_literals_behavior' => Parser::PARSE_JSON]);
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $this->assertObjectHasAttribute('people', $configObj);

        $array = [
            'boss' => [
                'name' => 'John',
                'age' => 42,
            ],
            'staff' => [
                [
                    'name' => 'Mark',
                    'age' => 35,
                ],
                [
                    'name' => 'Bill',
                    'age' => 44,
                ],
            ],
        ];

        $this->assertEquals($array, $config['people']);
    }

    public function testNoPropertyNesting()
    {
        $configObj = $this->getConfig('fixture03.ini',
            ['property_nesting' => false]);
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $expected = [
            'environment' => 'testing',
            'testing' => [
                'debug' => true,
                'database.connection' => 'mysql:host=127.0.0.1',
                'database.name' => 'test',
                'database.username' => '',
                'database.password' => '',
                'secrets' => [1, 2, 3],
            ],
            'staging' => [
                'debug' => true,
                'database.connection' => 'mysql:host=127.0.0.1',
                'database.name' => 'stage',
                'database.username' => 'staging',
                'database.password' => 12345,
                'secrets' => [1, 2, 3],
            ],
            'production' => [
                'debug' => false,
                'database.connection' => 'mysql:host=127.0.0.1',
                'database.name' => 'production',
                'database.username' => 'root',
                'database.password' => 12345,
                'secrets' => [1, 2, 3],
            ],
        ];

        $this->assertEquals($expected, $config);
    }

    public function testCanParseIniString()
    {
        $parser = new Parser();
        $parser->treat_ini_string = true;
        $parser->property_nesting = false;
        $configObj = $parser->parse(file_get_contents(__DIR__ . '/../fixtures/fixture13.ini'));

        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);

        $expected = [
            'environment' => 'testing',
            'testing' => [
                'debug' => true,
                'database.connection' => 'mysql:host=127.0.0.1',
                'database.name' => 'test',
                'database.username' => '',
                'database.password' => '',
                'secrets' => [1, 2, 3],
            ],
            'staging' => [
                'debug' => true,
                'database.connection' => 'mysql:host=127.0.0.1',
                'database.name' => 'stage',
                'database.username' => 'staging',
                'database.password' => 12345,
                'secrets' => [1, 2, 3],
            ],
            'production' => [
                'debug' => false,
                'database.connection' => 'mysql:host=127.0.0.1',
                'database.name' => 'production',
                'database.username' => 'root',
                'database.password' => 12345,
                'secrets' => [1, 2, 3],
            ],
        ];

        $this->assertEquals($expected, $config);
    }

    /**
     * Tests that appending to a potentially non-existent array works as expected
     * when also using property nesting.
     *
     * @return void
     */
    public function testNestedArrayAppend()
    {
        $configObj = $this->getConfig('fixture11.ini');
        $config = $this->phpUnitDoesNotUnderstandArrayObject($configObj);
        $expected = [1, 2, 'c'];

        $this->assertArrayHasKey('nesting', $config);
        $this->assertArrayHasKey('a', $config['nesting']);
        $this->assertEquals($expected, $config['nesting']['a']);
    }

    /**
     * Create a config array (from the given fixture).
     *
     * @param $file
     *
     * @return array
     */
    protected function getConfig($file, $options = [])
    {
        $parser = new Parser(__DIR__ . '/../fixtures/' . $file);
        foreach ($options as $key => $value) {
            $parser->$key = $value;
        }
        $config = $parser->parse();

        return $config;
    }

    /**
     * Tested with 3.6.x so far. See {@link PHPUnit_Runner_Version::id()}.
     *
     * @param \ArrayObject|array $config
     *
     * @return array
     */
    protected function phpUnitDoesNotUnderstandArrayObject(\ArrayObject $config)
    {
        $arr = (array)$config;
        foreach ($arr as $key => $value) {
            if ($value instanceof \ArrayObject) {
                $arr[$key] = $this->phpUnitDoesNotUnderstandArrayObject($value);
            }
        }

        return $arr;
    }
}
